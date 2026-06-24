// ============================================================
//  app.js — Frontend JavaScript V2
//  Authentification JWT + rôles + emprunt/retour
// ============================================================

// ---- Configuration ----
const API_BASE = "http://localhost:8000/api";

// ---- État de l'application ----
let books      = [];
let categories = [];
let editingId   = null;
let deleteTarget = null;

// État d'authentification. currentUser = null si non connecté.
// currentToken stocké en localStorage : survit au rechargement de
// la page, au prix d'une exposition au vol via une faille XSS —
// compromis assumé, classique pour ce type de projet, documenté
// dans le README plutôt que caché.
let currentUser  = null;
let currentToken = localStorage.getItem('jwt_token') || null;

// ---- Sélecteurs DOM ----
const booksGrid    = document.getElementById("booksGrid");
const loading       = document.getElementById("loading");
const emptyState     = document.getElementById("emptyState");
const bookCount       = document.getElementById("bookCount");
const toast            = document.getElementById("toast");
const categoryFilter    = document.getElementById("categoryFilter");
const categorySelect     = document.getElementById("category_id");

// Auth UI
const btnOpenLogin = document.getElementById("btnOpenLogin");
const userPill      = document.getElementById("userPill");
const userPillName   = document.getElementById("userPillName");
const userPillRole    = document.getElementById("userPillRole");
const btnLogout         = document.getElementById("btnLogout");
const btnOpenAdd          = document.getElementById("btnOpenAdd");

const loginOverlay = document.getElementById("loginOverlay");
const loginForm      = document.getElementById("loginForm");

// Modal formulaire livre
const modalOverlay = document.getElementById("modalOverlay");
const modalTitle      = document.getElementById("modalTitle");
const bookForm           = document.getElementById("bookForm");
const bookIdInput          = document.getElementById("bookId");

// Modal suppression
const confirmOverlay = document.getElementById("confirmOverlay");
const deleteTitle       = document.getElementById("deleteTitle");

// ============================================================
//  UTILITAIRES
// ============================================================

function showToast(message, type = "success") {
    toast.textContent = message;
    toast.className = `toast ${type}`;
    setTimeout(() => { toast.className = "toast hidden"; }, 3000);
}

function formatDate(dateStr) {
    if (!dateStr) return "—";
    const d = new Date(dateStr);
    return d.toLocaleDateString("fr-FR", { day: "2-digit", month: "short", year: "numeric" });
}

function escapeHtml(str) {
    const map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
    return String(str).replace(/[&<>"']/g, (m) => map[m]);
}

/**
 * Construit les headers HTTP communs, en ajoutant l'en-tête
 * Authorization SEULEMENT si un token est présent. Évite de
 * répéter cette logique dans chaque fonction d'appel API.
 */
function authHeaders(extra = {}) {
    const headers = { "Content-Type": "application/json", ...extra };
    if (currentToken) {
        headers["Authorization"] = `Bearer ${currentToken}`;
    }
    return headers;
}

// ============================================================
//  AUTHENTIFICATION
// ============================================================

/**
 * Tente de restaurer la session depuis le token déjà en
 * localStorage (au chargement de la page). Si le token est
 * expiré ou invalide, /api/auth/me répondra 401 et on nettoie.
 */
async function restoreSession() {
    if (!currentToken) {
        renderAuthUI();
        return;
    }

    try {
        const res = await fetch(`${API_BASE}/auth/me`, { headers: authHeaders() });
        if (!res.ok) throw new Error("Session invalide");

        const data = await res.json();
        currentUser = data.user;
    } catch (err) {
        // Token expiré ou invalide : on nettoie silencieusement,
        // pas besoin d'alarmer l'utilisateur pour une session qui
        // a simplement expiré dans le temps.
        currentToken = null;
        currentUser  = null;
        localStorage.removeItem('jwt_token');
    }

    renderAuthUI();
}

async function login(email, password) {
    const res  = await fetch(`${API_BASE}/auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password }),
    });
    const data = await res.json();

    if (data.success) {
        currentToken = data.token;
        currentUser  = data.user;
        localStorage.setItem('jwt_token', currentToken);
    }

    return data;
}

function logout() {
    currentToken = null;
    currentUser  = null;
    localStorage.removeItem('jwt_token');
    renderAuthUI();
    showToast("Déconnecté.", "success");
    loadBooks(); // recharge sans les actions emprunter/retourner
}

/**
 * Met à jour l'interface selon l'état d'authentification :
 * - non connecté  -> bouton "Se connecter" visible
 * - connecté membre -> pill utilisateur visible, pas de bouton admin
 * - connecté admin  -> pill utilisateur + bouton "Ajouter un livre"
 */
function renderAuthUI() {
    const isLoggedIn = currentUser !== null;
    const isAdmin    = isLoggedIn && currentUser.role === 'admin';

    btnOpenLogin.classList.toggle('hidden', isLoggedIn);
    userPill.classList.toggle('hidden', !isLoggedIn);
    btnOpenAdd.classList.toggle('hidden', !isAdmin);

    if (isLoggedIn) {
        userPillName.textContent = currentUser.full_name || currentUser.email;
        userPillRole.textContent = currentUser.role;
        userPillRole.className   = `badge badge-role role-${currentUser.role}`;
    }
}

// ============================================================
//  API CALLS — Catégories
// ============================================================

async function loadCategories() {
    try {
        const res  = await fetch(`${API_BASE}/categories`);
        const data = await res.json();
        if (data.success) {
            categories = data.data;
            populateCategorySelects();
        }
    } catch (err) {
        console.error("Erreur chargement catégories :", err);
    }
}

function populateCategorySelects() {
    const filterOptions = categories
        .map(c => `<option value="${c.id}">${escapeHtml(c.name)} (${c.books_count})</option>`)
        .join('');
    categoryFilter.innerHTML = '<option value="">Toutes les catégories</option>' + filterOptions;

    const formOptions = categories
        .map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`)
        .join('');
    categorySelect.innerHTML = '<option value="">Non classé</option>' + formOptions;
}

// ============================================================
//  API CALLS — Livres
// ============================================================

async function loadBooks() {
    loading.classList.remove("hidden");
    booksGrid.innerHTML = "";
    emptyState.classList.add("hidden");

    const search     = document.getElementById("searchInput").value.trim();
    const categoryId  = categoryFilter.value;
    const available    = document.getElementById("availFilter").value;

    const params = new URLSearchParams();
    if (search) params.set("search", search);
    if (categoryId) params.set("category_id", categoryId);
    if (available !== "") params.set("available", available);

    const url = `${API_BASE}/books${params.toString() ? "?" + params.toString() : ""}`;

    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`Erreur HTTP ${response.status}`);

        const data = await response.json();
        loading.classList.add("hidden");

        if (!data.success) {
            showToast("Erreur : " + data.message, "error");
            return;
        }

        books = data.data;
        bookCount.textContent = `${data.count} livre(s)`;

        if (books.length === 0) {
            emptyState.classList.remove("hidden");
            return;
        }

        // Si on est connecté, on récupère aussi les emprunts en cours
        // pour afficher QUI a emprunté chaque livre indisponible.
        let activeLoansByBook = {};
        if (currentUser && currentUser.role === 'admin') {
            activeLoansByBook = await fetchActiveLoansMap();
        }

        booksGrid.innerHTML = books.map(b => buildBookCard(b, activeLoansByBook)).join("");
    } catch (err) {
        loading.classList.add("hidden");
        showToast("Impossible de contacter l'API.", "error");
        console.error(err);
    }
}

/**
 * Récupère les emprunts actifs et les indexe par book_id, pour
 * un accès O(1) lors de la construction de chaque carte plutôt
 * que de chercher dans un tableau à chaque fois.
 */
async function fetchActiveLoansMap() {
    try {
        const res  = await fetch(`${API_BASE}/loans?status=active`, { headers: authHeaders() });
        const data = await res.json();
        if (!data.success) return {};

        const map = {};
        data.data.forEach(loan => { map[loan.book_id] = loan; });
        return map;
    } catch {
        return {};
    }
}

function buildBookCard(book, activeLoansByBook = {}) {
    const availClass = book.available == 1 ? "badge-available" : "badge-borrowed";
    const availLabel = book.available == 1 ? "✓ Disponible" : "✗ Emprunté";
    const categoryName = book.category_name || "Non classé";

    const loan = activeLoansByBook[book.id];
    const borrowerLine = loan
        ? `<p class="book-borrower">Emprunté par ${escapeHtml(loan.borrower_name)} · retour prévu le ${formatDate(loan.due_at)}</p>`
        : '';

    const isLoggedIn = currentUser !== null;
    const isAdmin    = isLoggedIn && currentUser.role === 'admin';

    // Actions emprunter/retourner : visibles seulement si connecté.
    let loanAction = '';
    if (isLoggedIn) {
        loanAction = book.available == 1
            ? `<button class="btn btn-primary" onclick="handleBorrow(${book.id})">Emprunter</button>`
            : `<button class="btn btn-outline" onclick="handleReturn(${book.id})">Retourner</button>`;
    }

    // Actions admin : modifier/supprimer.
    const adminActions = isAdmin
        ? `<button class="btn btn-icon" title="Modifier" onclick="openEditModal(${book.id})">✏️</button>
           <button class="btn btn-icon" title="Supprimer" onclick="openDeleteConfirm(${book.id}, '${escapeHtml(book.title).replace(/'/g,"\\'")}')">🗑️</button>`
        : '';

    return `
    <article class="book-card" data-id="${book.id}">
        <div class="book-card-header">
            <h3 class="book-title">${escapeHtml(book.title)}</h3>
            <span class="book-id">#${book.id}</span>
        </div>
        <p class="book-author">✍ ${escapeHtml(book.author)}</p>
        <div class="book-meta">
            <span class="badge badge-genre">${escapeHtml(categoryName)}</span>
            <span class="badge badge-year">${book.year}</span>
            <span class="badge ${availClass}">${availLabel}</span>
        </div>
        ${borrowerLine}
        <p class="book-date">Ajouté le ${formatDate(book.created_at)}</p>
        <div class="book-actions">
            ${loanAction}
            ${adminActions}
        </div>
    </article>
    `;
}

// ============================================================
//  EMPRUNTER / RETOURNER
// ============================================================

async function handleBorrow(bookId) {
    if (!currentUser) {
        showToast("Connecte-toi pour emprunter un livre.", "error");
        return;
    }

    try {
        const res  = await fetch(`${API_BASE}/loans/borrow`, {
            method: "POST",
            headers: authHeaders(),
            body: JSON.stringify({ book_id: bookId }),
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            loadBooks();
        } else {
            showToast("Erreur : " + data.message, "error");
        }
    } catch (err) {
        showToast("Erreur réseau.", "error");
    }
}

async function handleReturn(bookId) {
    try {
        const res  = await fetch(`${API_BASE}/loans/return`, {
            method: "POST",
            headers: authHeaders(),
            body: JSON.stringify({ book_id: bookId }),
        });
        const data = await res.json();

        if (data.success) {
            showToast(data.message, "success");
            loadBooks();
        } else {
            showToast("Erreur : " + data.message, "error");
        }
    } catch (err) {
        showToast("Erreur réseau.", "error");
    }
}

// ============================================================
//  CRUD LIVRES (admin) — POST/PUT/DELETE avec header Authorization
// ============================================================

async function fetchBook(id) {
    const response = await fetch(`${API_BASE}/books/${id}`);
    const data = await response.json();
    return data.success ? data.data : null;
}

async function createBook(payload) {
    const response = await fetch(`${API_BASE}/books`, {
        method: "POST",
        headers: authHeaders(),
        body: JSON.stringify(payload),
    });
    return await response.json();
}

async function updateBook(id, payload) {
    const response = await fetch(`${API_BASE}/books/${id}`, {
        method: "PUT",
        headers: authHeaders(),
        body: JSON.stringify(payload),
    });
    return await response.json();
}

async function deleteBook(id) {
    const response = await fetch(`${API_BASE}/books/${id}`, {
        method: "DELETE",
        headers: authHeaders(),
    });
    return await response.json();
}

// ============================================================
//  MODAL LOGIN
// ============================================================

function openLoginModal() {
    loginForm.reset();
    document.getElementById("errLoginEmail").textContent = "";
    document.getElementById("errLoginPassword").textContent = "";
    loginOverlay.classList.remove("hidden");
}

function closeLoginModal() {
    loginOverlay.classList.add("hidden");
}

loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const email    = document.getElementById("loginEmail").value.trim();
    const password = document.getElementById("loginPassword").value;

    document.getElementById("errLoginEmail").textContent = "";
    document.getElementById("errLoginPassword").textContent = "";

    document.getElementById("btnSubmitLogin").textContent = "Connexion…";

    try {
        const result = await login(email, password);

        if (result.success) {
            showToast(`Bienvenue, ${result.user.full_name} !`, "success");
            closeLoginModal();
            renderAuthUI();
            loadBooks();
        } else {
            document.getElementById("errLoginPassword").textContent = result.message;
        }
    } catch (err) {
        showToast("Erreur réseau.", "error");
    } finally {
        document.getElementById("btnSubmitLogin").textContent = "Se connecter";
    }
});

// ============================================================
//  MODAL FORMULAIRE LIVRE (admin)
// ============================================================

function openAddModal() {
    editingId = null;
    modalTitle.textContent = "Ajouter un livre";
    bookForm.reset();
    bookIdInput.value = "";
    clearFormErrors();
    document.getElementById("available").checked = true;
    modalOverlay.classList.remove("hidden");
}

async function openEditModal(id) {
    const book = await fetchBook(id);
    if (!book) {
        showToast("Livre introuvable.", "error");
        return;
    }

    editingId = id;
    modalTitle.textContent = "Modifier le livre";

    document.getElementById("title").value = book.title;
    document.getElementById("author").value = book.author;
    document.getElementById("category_id").value = book.category_id || "";
    document.getElementById("year").value = book.year;
    document.getElementById("available").checked = book.available == 1;
    clearFormErrors();

    modalOverlay.classList.remove("hidden");
}

function closeModal() {
    modalOverlay.classList.add("hidden");
    editingId = null;
}

function clearFormErrors() {
    ["Title", "Author", "Category", "Year"].forEach((f) => {
        const el = document.getElementById("err" + f);
        if (el) el.textContent = "";
    });
}

function validateForm(data) {
    let valid = true;
    clearFormErrors();

    if (!data.title.trim()) {
        document.getElementById("errTitle").textContent = "Le titre est requis.";
        valid = false;
    }
    if (!data.author.trim()) {
        document.getElementById("errAuthor").textContent = "L'auteur est requis.";
        valid = false;
    }
    const yr = parseInt(data.year);
    if (!data.year || isNaN(yr) || yr < 1000 || yr > 2099) {
        document.getElementById("errYear").textContent = "Année invalide (1000-2099).";
        valid = false;
    }
    return valid;
}

bookForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const payload = {
        title: document.getElementById("title").value,
        author: document.getElementById("author").value,
        category_id: document.getElementById("category_id").value || null,
        year: parseInt(document.getElementById("year").value),
        available: document.getElementById("available").checked ? 1 : 0,
    };

    if (!validateForm(payload)) return;

    document.getElementById("btnSubmitForm").textContent = "Enregistrement…";

    try {
        const result = editingId
            ? await updateBook(editingId, payload)
            : await createBook(payload);

        if (result.success) {
            showToast(result.message || "Livre enregistré.", "success");
            closeModal();
            loadBooks();
        } else {
            const errMsg = result.errors ? result.errors.join(", ") : (result.message || "Erreur inconnue.");
            showToast("Erreur : " + errMsg, "error");
        }
    } catch (err) {
        showToast("Erreur réseau.", "error");
    } finally {
        document.getElementById("btnSubmitForm").textContent = "Enregistrer";
    }
});

// ============================================================
//  SUPPRESSION (admin)
// ============================================================

function openDeleteConfirm(id, title) {
    deleteTarget = id;
    deleteTitle.textContent = title;
    confirmOverlay.classList.remove("hidden");
}

document.getElementById("btnConfirmDelete").addEventListener("click", async () => {
    if (!deleteTarget) return;

    try {
        const result = await deleteBook(deleteTarget);
        confirmOverlay.classList.add("hidden");

        if (result.success) {
            showToast(result.message, "success");
            loadBooks();
        } else {
            showToast("Erreur : " + result.message, "error");
        }
    } catch (err) {
        showToast("Erreur réseau.", "error");
    } finally {
        deleteTarget = null;
    }
});

// ============================================================
//  ÉVÉNEMENTS GÉNÉRAUX
// ============================================================

btnOpenLogin.addEventListener("click", openLoginModal);
document.getElementById("btnCloseLogin").addEventListener("click", closeLoginModal);
document.getElementById("btnCancelLogin").addEventListener("click", closeLoginModal);
btnLogout.addEventListener("click", logout);

btnOpenAdd.addEventListener("click", openAddModal);
document.getElementById("btnCloseModal").addEventListener("click", closeModal);
document.getElementById("btnCancelForm").addEventListener("click", closeModal);
document.getElementById("btnCancelDelete").addEventListener("click", () => {
    confirmOverlay.classList.add("hidden");
    deleteTarget = null;
});

loginOverlay.addEventListener("click", (e) => { if (e.target === loginOverlay) closeLoginModal(); });
modalOverlay.addEventListener("click", (e) => { if (e.target === modalOverlay) closeModal(); });
confirmOverlay.addEventListener("click", (e) => {
    if (e.target === confirmOverlay) {
        confirmOverlay.classList.add("hidden");
        deleteTarget = null;
    }
});

document.getElementById("btnFilter").addEventListener("click", loadBooks);
document.getElementById("btnReset").addEventListener("click", () => {
    document.getElementById("searchInput").value = "";
    categoryFilter.value = "";
    document.getElementById("availFilter").value = "";
    loadBooks();
});
document.getElementById("searchInput").addEventListener("keydown", (e) => {
    if (e.key === "Enter") loadBooks();
});

// ============================================================
//  INITIALISATION
// ============================================================
async function init() {
    await restoreSession();   // vérifie le token localStorage, restaure currentUser
    await loadCategories();   // remplit les <select>
    await loadBooks();        // affiche les livres (avec ou sans actions selon l'auth)
}

document.addEventListener("DOMContentLoaded", init);