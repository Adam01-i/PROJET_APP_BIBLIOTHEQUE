// ============================================================
//  app.js — Frontend JavaScript V3
//  Correctifs : apiFetch centralisé, logique Retourner basée sur
//  la propriété réelle de l'emprunt, /auth/me robuste, déconnexion
//  automatique sur 401.
// ============================================================

const API_BASE = "http://localhost:8000/api";

// ---- État de l'application ----
let books          = [];
let categories      = [];
let activeLoans      = [];   // Emprunts actifs, chargés pour TOUT utilisateur connecté
let editingId          = null;
let deleteTarget         = null;

let currentUser  = null;
let currentToken = localStorage.getItem('jwt_token') || null;

// ---- Sélecteurs DOM ----
const booksGrid       = document.getElementById("booksGrid");
const loading           = document.getElementById("loading");
const emptyState          = document.getElementById("emptyState");
const bookCount             = document.getElementById("bookCount");
const toast                   = document.getElementById("toast");
const categoryFilter           = document.getElementById("categoryFilter");
const categorySelect             = document.getElementById("category_id");

const btnOpenLogin = document.getElementById("btnOpenLogin");
const userPill       = document.getElementById("userPill");
const userPillName     = document.getElementById("userPillName");
const userPillRole       = document.getElementById("userPillRole");
const btnLogout            = document.getElementById("btnLogout");
const btnOpenAdd             = document.getElementById("btnOpenAdd");

const loginOverlay = document.getElementById("loginOverlay");
const loginForm       = document.getElementById("loginForm");

const modalOverlay = document.getElementById("modalOverlay");
const modalTitle      = document.getElementById("modalTitle");
const bookForm           = document.getElementById("bookForm");
const bookIdInput          = document.getElementById("bookId");

const confirmOverlay = document.getElementById("confirmOverlay");
const deleteTitle       = document.getElementById("deleteTitle");

// ============================================================
//  COUCHE API CENTRALISÉE
// ============================================================

/**
 * apiFetch() — point d'entrée UNIQUE pour tous les appels API.
 *
 * Centralise :
 * - l'ajout du header Authorization si un token existe
 * - le parsing JSON systématique
 * - la détection des erreurs réseau (fetch qui rejette)
 * - la détection des 401 -> déconnexion automatique + toast
 * - la détection des success:false -> retour uniforme
 *
 * Toute fonction d'appel API du reste du fichier passe par celle-ci
 * plutôt que d'appeler fetch() directement — ça évite que chaque
 * fonction réinvente sa propre gestion d'erreur, avec le risque
 * d'oublier un cas (ex: oublier de gérer un 401 dans une nouvelle
 * fonction ajoutée plus tard).
 *
 * @param string $path     Chemin relatif à API_BASE (ex: "/books")
 * @param object $options  Options fetch standard (method, body...)
 * @returns {Promise<{ok: boolean, status: number, data: object}>}
 */
async function apiFetch(path, options = {}) {
    const headers = { "Content-Type": "application/json", ...(options.headers || {}) };
    if (currentToken) {
        headers["Authorization"] = `Bearer ${currentToken}`;
    }

    let response;
    try {
        response = await fetch(`${API_BASE}${path}`, { ...options, headers });
    } catch (networkErr) {
        // fetch() rejette uniquement sur des erreurs réseau réelles
        // (serveur injoignable, CORS bloqué, etc.) — pas sur un 4xx/5xx,
        // qui sont des réponses HTTP valides que fetch() ne fait jamais
        // échouer automatiquement.
        return { ok: false, status: 0, data: { success: false, message: "Impossible de contacter le serveur." } };
    }

    let data;
    try {
        data = await response.json();
    } catch (parseErr) {
        // Réponse reçue mais pas du JSON valide (ex: page d'erreur HTML
        // renvoyée par Apache sur un crash PHP non géré).
        return { ok: false, status: response.status, data: { success: false, message: "Réponse du serveur illisible." } };
    }

    // 401 = le token est absent, invalide, ou expiré. On nettoie la
    // session automatiquement plutôt que de laisser l'UI prétendre
    // que l'utilisateur est toujours connecté alors que le serveur
    // le rejette désormais sur chaque requête.
    if (response.status === 401 && currentUser !== null) {
        clearSession();
        showToast("Session expirée. Reconnecte-toi.", "error");
        renderAuthUI();
        loadBooks();
    }

    return { ok: response.ok, status: response.status, data };
}

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

/** Vide proprement l'état de session, en mémoire ET en localStorage. */
function clearSession() {
    currentToken = null;
    currentUser  = null;
    localStorage.removeItem('jwt_token');
}

// ============================================================
//  AUTHENTIFICATION
// ============================================================

/**
 * Restaure la session depuis le token localStorage au chargement.
 *
 * Robuste au fait que /auth/me peut structurellement renvoyer un
 * objet user différent de /auth/login (ex: full_name absent) : on
 * fusionne avec des valeurs de repli plutôt que de supposer la
 * présence de chaque champ.
 */
async function restoreSession() {
    if (!currentToken) {
        renderAuthUI();
        return;
    }

    const { ok, data } = await apiFetch('/auth/me');

    if (!ok || !data.success) {
        clearSession();
        renderAuthUI();
        return;
    }

    currentUser = normalizeUser(data.user);
    renderAuthUI();
}

/**
 * Normalise un objet "user" venant de l'API, qui peut avoir des
 * formes légèrement différentes selon l'endpoint (/login vs /me).
 * Garantit que currentUser a toujours les mêmes clés disponibles,
 * pour que le reste du code n'ait jamais à se demander "est-ce que
 * full_name existe cette fois-ci ?".
 */
function normalizeUser(rawUser) {
    return {
        id: rawUser.id,
        full_name: rawUser.full_name || rawUser.email || 'Utilisateur',
        email: rawUser.email,
        role: rawUser.role,
    };
}

async function login(email, password) {
    const { data } = await apiFetch('/auth/login', {
        method: "POST",
        body: JSON.stringify({ email, password }),
    });

    if (data.success) {
        currentToken = data.token;
        currentUser  = normalizeUser(data.user);
        localStorage.setItem('jwt_token', currentToken);
    }

    return data;
}

function logout() {
    clearSession();
    renderAuthUI();
    showToast("Déconnecté.", "success");
    loadBooks();
}

function renderAuthUI() {
    const isLoggedIn = currentUser !== null;
    const isAdmin    = isLoggedIn && currentUser.role === 'admin';

    btnOpenLogin.classList.toggle('hidden', isLoggedIn);
    userPill.classList.toggle('hidden', !isLoggedIn);
    btnOpenAdd.classList.toggle('hidden', !isAdmin);

    if (isLoggedIn) {
        userPillName.textContent = currentUser.full_name;
        userPillRole.textContent = currentUser.role;
        userPillRole.className   = `badge badge-role role-${currentUser.role}`;
    }
}

// ============================================================
//  CATÉGORIES
// ============================================================

async function loadCategories() {
    const { ok, data } = await apiFetch('/categories');
    if (ok && data.success) {
        categories = data.data;
        populateCategorySelects();
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
//  EMPRUNTS ACTIFS — chargés pour TOUT utilisateur connecté
// ============================================================

/**
 * CORRECTIF V3 : chargé pour n'importe quel utilisateur connecté
 * (pas seulement admin). Un membre a besoin de savoir si UN emprunt
 * actif sur un livre donné lui appartient, pour décider d'afficher
 * "Retourner". Sans cette donnée, impossible de distinguer "ce livre
 * est emprunté par moi" de "ce livre est emprunté par quelqu'un d'autre".
 *
 * Note sur la portée des données renvoyées :
 * - Pour un membre, /api/loans?status=active est déjà filtré côté
 *   backend (LoanController::getAll force user_id = lui-même) : il
 *   ne reçoit QUE ses propres emprunts actifs.
 * - Pour un admin, la même route renvoie TOUS les emprunts actifs.
 * Le frontend n'a donc jamais besoin de filtrer davantage : la
 * portée est déjà correcte selon le rôle, garantie par le backend.
 */
async function loadActiveLoans() {
    if (!currentUser) {
        activeLoans = [];
        return;
    }

    const { ok, data } = await apiFetch('/loans?status=active');
    activeLoans = (ok && data.success) ? data.data : [];
}

/** Trouve l'emprunt actif d'un livre donné parmi ceux déjà chargés. */
function findActiveLoanForBook(bookId) {
    return activeLoans.find(l => l.book_id === bookId) || null;
}

// ============================================================
//  LIVRES
// ============================================================

async function loadBooks() {
    loading.classList.remove("hidden");
    booksGrid.innerHTML = "";
    emptyState.classList.add("hidden");

    const search     = document.getElementById("searchInput").value.trim();
    const categoryId  = categoryFilter.value;
    const available     = document.getElementById("availFilter").value;

    const params = new URLSearchParams();
    if (search) params.set("search", search);
    if (categoryId) params.set("category_id", categoryId);
    if (available !== "") params.set("available", available);

    // Recharge toujours les emprunts actifs en même temps que les
    // livres : ce sont deux vues de la même réalité (disponibilité),
    // elles doivent rester synchronisées à chaque rafraîchissement.
    await loadActiveLoans();

    const { ok, data } = await apiFetch(`/books${params.toString() ? "?" + params.toString() : ""}`);
    loading.classList.add("hidden");

    if (!ok || !data.success) {
        showToast("Erreur : " + (data.message || "impossible de charger les livres."), "error");
        return;
    }

    books = data.data;
    bookCount.textContent = `${data.count} livre(s)`;

    if (books.length === 0) {
        emptyState.classList.remove("hidden");
        return;
    }

    booksGrid.innerHTML = books.map(buildBookCard).join("");
}

/**
 * Construit la carte d'un livre, avec la logique d'actions corrigée :
 *
 * - Non connecté            -> aucune action (juste consultation)
 * - Connecté, livre dispo   -> bouton "Emprunter"
 * - Connecté, livre indispo, emprunt = le mien      -> bouton "Retourner"
 * - Connecté, livre indispo, emprunt = quelqu'un d'autre -> AUCUN
 *   bouton d'emprunt, juste l'info "emprunté par X" si admin
 * - Admin -> peut toujours "Retourner" n'importe quel livre indisponible
 *   (cohérent avec le backend : un admin retourne pour n'importe qui)
 */
function buildBookCard(book) {
    const availClass = book.available == 1 ? "badge-available" : "badge-borrowed";
    const availLabel = book.available == 1 ? "✓ Disponible" : "✗ Emprunté";
    const categoryName = book.category_name || "Non classé";

    const isLoggedIn = currentUser !== null;
    const isAdmin    = isLoggedIn && currentUser.role === 'admin';
    const loan       = findActiveLoanForBook(book.id);
    const isMyLoan   = loan !== null && isLoggedIn && loan.user_id === currentUser.id;

    // Ligne d'info "emprunté par" : affichée seulement si on connaît
    // l'emprunteur (admin voit tout ; un membre ne voit cette ligne
    // QUE pour son propre emprunt, car activeLoans ne contient déjà
    // que les emprunts auxquels il a droit de regard côté backend).
    const borrowerLine = (book.available == 0 && loan)
        ? `<p class="book-borrower">Emprunté par ${escapeHtml(loan.borrower_name)} · retour prévu le ${formatDate(loan.due_at)}</p>`
        : '';

    let loanAction = '';
    if (isLoggedIn) {
        if (book.available == 1) {
            loanAction = `<button class="btn btn-primary" onclick="handleBorrow(${book.id})">Emprunter</button>`;
        } else if (isMyLoan || isAdmin) {
            // Un admin peut retourner n'importe quel emprunt actif ;
            // un membre seulement le sien (isMyLoan).
            loanAction = `<button class="btn btn-outline" onclick="handleReturn(${book.id})">Retourner</button>`;
        }
        // Cas restant (livre indisponible, emprunté par quelqu'un
        // d'autre, utilisateur = membre non-admin) : pas de bouton du
        // tout, volontairement — il n'y a rien que ce membre puisse
        // légitimement faire sur ce livre tant qu'il est emprunté.
    }

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

    const { data } = await apiFetch('/loans/borrow', {
        method: "POST",
        body: JSON.stringify({ book_id: bookId }),
    });

    if (data.success) {
        showToast(data.message, "success");
        loadBooks();
    } else {
        showToast("Erreur : " + data.message, "error");
    }
}

async function handleReturn(bookId) {
    const { data } = await apiFetch('/loans/return', {
        method: "POST",
        body: JSON.stringify({ book_id: bookId }),
    });

    if (data.success) {
        showToast(data.message, "success");
        loadBooks();
    } else {
        showToast("Erreur : " + data.message, "error");
    }
}

// ============================================================
//  CRUD LIVRES (admin)
// ============================================================

async function fetchBook(id) {
    const { ok, data } = await apiFetch(`/books/${id}`);
    return (ok && data.success) ? data.data : null;
}

async function createBook(payload) {
    const { data } = await apiFetch('/books', { method: "POST", body: JSON.stringify(payload) });
    return data;
}

async function updateBook(id, payload) {
    const { data } = await apiFetch(`/books/${id}`, { method: "PUT", body: JSON.stringify(payload) });
    return data;
}

async function deleteBook(id) {
    const { data } = await apiFetch(`/books/${id}`, { method: "DELETE" });
    return data;
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
            showToast(`Bienvenue, ${currentUser.full_name} !`, "success");
            closeLoginModal();
            renderAuthUI();
            loadBooks();
        } else {
            document.getElementById("errLoginPassword").textContent = result.message || "Connexion impossible.";
        }
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
            showToast(result.message || "Livre supprimé.", "success");
            loadBooks();
        } else {
            showToast("Erreur : " + result.message, "error");
        }
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
    await restoreSession();
    await loadCategories();
    await loadBooks();
}

document.addEventListener("DOMContentLoaded", init);