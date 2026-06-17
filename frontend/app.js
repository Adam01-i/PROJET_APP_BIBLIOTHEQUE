// ============================================================
//  app.js — Frontend JavaScript
//  Communication avec l'API REST via fetch()
//  Opérations : Lire, Ajouter, Modifier, Supprimer
// ============================================================

// ---- Configuration ----
// URL de base de l'API — adapter selon ton serveur
const API_BASE = "http://localhost:8000/api/books";

// ---- État de l'application ----
let books = []; // Liste des livres en mémoire
let editingId = null; // null = mode création, number = mode édition
let deleteTarget = null; // ID du livre à supprimer (confirmation)

// ---- Sélecteurs DOM ----
const booksGrid = document.getElementById("booksGrid");
const loading = document.getElementById("loading");
const emptyState = document.getElementById("emptyState");
const bookCount = document.getElementById("bookCount");
const toast = document.getElementById("toast");

// Modal formulaire
const modalOverlay = document.getElementById("modalOverlay");
const modalTitle = document.getElementById("modalTitle");
const bookForm = document.getElementById("bookForm");
const bookIdInput = document.getElementById("bookId");

// Modal confirmation suppression
const confirmOverlay = document.getElementById("confirmOverlay");
const deleteTitle = document.getElementById("deleteTitle");

// ============================================================
//  UTILITAIRES
// ============================================================

/**
 * Afficher une notification toast temporaire
 * @param {string} message  Texte à afficher
 * @param {string} type     'success' | 'error'
 */
function showToast(message, type = "success") {
  toast.textContent = message;
  toast.className = `toast ${type}`;
  // Après 3s, on cache le toast
  setTimeout(() => {
    toast.className = "toast hidden";
  }, 3000);
}

/**
 * Formater une date ISO en format lisible
 * @param {string} dateStr  Chaîne de date ISO
 * @returns {string}
 */
function formatDate(dateStr) {
  if (!dateStr) return "—";
  const d = new Date(dateStr);
  return d.toLocaleDateString("fr-FR", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

/**
 * Construire le HTML d'une carte livre
 * @param {Object} book Objet livre de l'API
 * @returns {string} HTML complet de la carte
 */
function buildBookCard(book) {
  const availClass = book.available == 1 ? "badge-available" : "badge-borrowed";
  const availLabel = book.available == 1 ? "✓ Disponible" : "✗ Emprunté";

  return `
    <article class="book-card" data-id="${book.id}">
        <div class="book-card-header">
            <h3 class="book-title">${escapeHtml(book.title)}</h3>
            <span class="book-id">#${book.id}</span>
        </div>
        <p class="book-author">✍ ${escapeHtml(book.author)}</p>
        <div class="book-meta">
            <span class="badge badge-genre">${escapeHtml(book.genre)}</span>
            <span class="badge badge-year">${book.year}</span>
            <span class="badge ${availClass}">${availLabel}</span>
        </div>
        <p class="book-date">Ajouté le ${formatDate(book.created_at)}</p>
        <div class="book-actions">
            <button class="btn btn-icon" title="Modifier" onclick="openEditModal(${book.id})">✏️</button>
            <button class="btn btn-icon" title="Supprimer" onclick="openDeleteConfirm(${book.id}, '${escapeHtml(book.title).replace(/'/g, "\\'")}')">🗑️</button>
        </div>
    </article>
    `;
}

/**
 * Échapper les caractères HTML pour éviter les XSS
 * @param {string} str
 * @returns {string}
 */
function escapeHtml(str) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  };
  return String(str).replace(/[&<>"']/g, (m) => map[m]);
}

// ============================================================
//  API CALLS (fetch)
// ============================================================

/**
 * GET /api/books — Charger tous les livres avec filtres optionnels
 */
async function loadBooks() {
  loading.classList.remove("hidden");
  booksGrid.innerHTML = "";
  emptyState.classList.add("hidden");

  // Construire la query string à partir des filtres
  const search = document.getElementById("searchInput").value.trim();
  const genre = document.getElementById("genreFilter").value;
  const available = document.getElementById("availFilter").value;

  const params = new URLSearchParams();
  if (search) params.set("search", search);
  if (genre) params.set("genre", genre);
  if (available !== "") params.set("available", available);

  const url = `${API_BASE}${params.toString() ? "?" + params.toString() : ""}`;

  try {
    // fetch() est la méthode native pour faire des requêtes HTTP en JS
    const response = await fetch(url);
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

    // Construire le HTML de chaque carte et l'injecter dans la grille
    booksGrid.innerHTML = books.map(buildBookCard).join("");
  } catch (err) {
    loading.classList.add("hidden");
    showToast("Impossible de contacter l'API. Serveur démarré ?", "error");
    console.error(err);
  }
}

/**
 * GET /api/books/{id} — Charger un livre par ID (pour pré-remplir le formulaire)
 * @param {number} id
 * @returns {Object|null}
 */
async function fetchBook(id) {
  const response = await fetch(`${API_BASE}/${id}`);
  const data = await response.json();
  return data.success ? data.data : null;
}

/**
 * POST /api/books — Créer un nouveau livre
 * @param {Object} payload Données du formulaire
 */
async function createBook(payload) {
  const response = await fetch(API_BASE, {
    method: "POST",
    // Content-Type: application/json dit au serveur que le corps est du JSON
    headers: { "Content-Type": "application/json" },
    // JSON.stringify() convertit l'objet JS en chaîne JSON
    body: JSON.stringify(payload),
  });
  return await response.json();
}

/**
 * PUT /api/books/{id} — Mettre à jour un livre
 * @param {number} id
 * @param {Object} payload
 */
async function updateBook(id, payload) {
  const response = await fetch(`${API_BASE}/${id}`, {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  return await response.json();
}

/**
 * DELETE /api/books/{id} — Supprimer un livre
 * @param {number} id
 */
async function deleteBook(id) {
  const response = await fetch(`${API_BASE}/${id}`, {
    method: "DELETE",
  });
  return await response.json();
}

// ============================================================
//  MODAL FORMULAIRE
// ============================================================

/** Ouvrir le modal en mode "Ajouter" */
function openAddModal() {
  editingId = null;
  modalTitle.textContent = "Ajouter un livre";
  bookForm.reset();
  bookIdInput.value = "";
  clearFormErrors();
  document.getElementById("available").checked = true;
  modalOverlay.classList.remove("hidden");
}

/** Ouvrir le modal en mode "Modifier" */
async function openEditModal(id) {
  const book = await fetchBook(id);
  if (!book) {
    showToast("Livre introuvable.", "error");
    return;
  }

  editingId = id;
  modalTitle.textContent = "Modifier le livre";

  // Pré-remplir les champs du formulaire
  document.getElementById("title").value = book.title;
  document.getElementById("author").value = book.author;
  document.getElementById("genre").value = book.genre;
  document.getElementById("year").value = book.year;
  document.getElementById("available").checked = book.available == 1;
  clearFormErrors();

  modalOverlay.classList.remove("hidden");
}

/** Fermer le modal formulaire */
function closeModal() {
  modalOverlay.classList.add("hidden");
  editingId = null;
}

/** Effacer les messages d'erreur du formulaire */
function clearFormErrors() {
  ["Title", "Author", "Genre", "Year"].forEach((f) => {
    document.getElementById("err" + f).textContent = "";
  });
}

/** Valider le formulaire côté client */
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
  if (!data.genre.trim()) {
    document.getElementById("errGenre").textContent = "Le genre est requis.";
    valid = false;
  }
  const yr = parseInt(data.year);
  if (!data.year || isNaN(yr) || yr < 1000 || yr > 2099) {
    document.getElementById("errYear").textContent =
      "Année invalide (1000-2099).";
    valid = false;
  }
  return valid;
}

// ============================================================
//  SOUMISSION DU FORMULAIRE
// ============================================================

bookForm.addEventListener("submit", async (e) => {
  e.preventDefault(); // Empêcher le rechargement de la page

  const payload = {
    title: document.getElementById("title").value,
    author: document.getElementById("author").value,
    genre: document.getElementById("genre").value,
    year: parseInt(document.getElementById("year").value),
    available: document.getElementById("available").checked ? 1 : 0,
  };

  if (!validateForm(payload)) return;

  document.getElementById("btnSubmitForm").textContent = "Enregistrement…";

  try {
    let result;
    if (editingId) {
      result = await updateBook(editingId, payload);
    } else {
      result = await createBook(payload);
    }

    if (result.success) {
      showToast(result.message, "success");
      closeModal();
      loadBooks(); // Recharger la liste
    } else {
      const errMsg = result.errors ? result.errors.join(", ") : result.message;
      showToast("Erreur : " + errMsg, "error");
    }
  } catch (err) {
    showToast("Erreur réseau.", "error");
  } finally {
    document.getElementById("btnSubmitForm").textContent = "Enregistrer";
  }
});

// ============================================================
//  SUPPRESSION (avec confirmation)
// ============================================================

function openDeleteConfirm(id, title) {
  deleteTarget = id;
  deleteTitle.textContent = title;
  confirmOverlay.classList.remove("hidden");
}

document
  .getElementById("btnConfirmDelete")
  .addEventListener("click", async () => {
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

document.getElementById("btnOpenAdd").addEventListener("click", openAddModal);
document.getElementById("btnCloseModal").addEventListener("click", closeModal);
document.getElementById("btnCancelForm").addEventListener("click", closeModal);
document.getElementById("btnCancelDelete").addEventListener("click", () => {
  confirmOverlay.classList.add("hidden");
  deleteTarget = null;
});

// Fermer le modal en cliquant sur le fond
modalOverlay.addEventListener("click", (e) => {
  if (e.target === modalOverlay) closeModal();
});
confirmOverlay.addEventListener("click", (e) => {
  if (e.target === confirmOverlay) {
    confirmOverlay.classList.add("hidden");
    deleteTarget = null;
  }
});

// Bouton filtrer et réinitialiser
document.getElementById("btnFilter").addEventListener("click", loadBooks);
document.getElementById("btnReset").addEventListener("click", () => {
  document.getElementById("searchInput").value = "";
  document.getElementById("genreFilter").value = "";
  document.getElementById("availFilter").value = "";
  loadBooks();
});

// Filtrer en appuyant sur Entrée
document.getElementById("searchInput").addEventListener("keydown", (e) => {
  if (e.key === "Enter") loadBooks();
});

// ============================================================
//  TESTEUR D'API INTÉGRÉ
// ============================================================

document.getElementById("btnSendApi").addEventListener("click", async () => {
  const method = document.getElementById("apiMethod").value;
  let url = document.getElementById("apiUrl").value.trim();

  if (url.startsWith("/")) {
    url = "http://localhost:8000" + url;
  }
  const body = document.getElementById("apiBody").value.trim();
  const result = document.getElementById("apiResult");
  const status = document.getElementById("apiStatus");

  result.textContent = "Chargement…";
  status.textContent = "";

  const options = {
    method,
    headers: { "Content-Type": "application/json" },
  };

  if (["POST", "PUT"].includes(method) && body) {
    try {
      options.body = JSON.stringify(JSON.parse(body)); // Valider le JSON
    } catch {
      result.textContent = "❌ JSON invalide dans le corps de la requête.";
      return;
    }
  }

  try {
    const res = await fetch(url, options);
    const data = await res.json();

    const statusOk = res.ok;
    status.textContent = `HTTP ${res.status}`;
    status.className = "api-status " + (statusOk ? "ok" : "err");
    result.textContent = JSON.stringify(data, null, 2);

    // Recharger les livres si la requête a modifié des données
    if (["POST", "PUT", "DELETE"].includes(method) && res.ok) {
      loadBooks();
    }
  } catch (err) {
    status.textContent = "ERREUR";
    status.className = "api-status err";
    result.textContent =
      "❌ " + err.message + "\n\nVérifiez que le serveur PHP est démarré.";
  }
});

// ============================================================
//  INITIALISATION
// ============================================================
document.addEventListener("DOMContentLoaded", loadBooks);
