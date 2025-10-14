let currentUser = null;

// Check authentication
function checkAuth() {
    const token = localStorage.getItem('token');
    const user = localStorage.getItem('user');
    
    if (!token || !user) {
        window.location.href = 'index.html';
        return null;
    }
    
    return JSON.parse(user);
}

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    currentUser = checkAuth();
    if (!currentUser) return;
    
    initializeUI();
    loadBooks();
});

function initializeUI() {
    document.getElementById('user-info').textContent = 
        `${currentUser.username} (${currentUser.role})`;
    
    // Show/hide features based on role
    if (currentUser.role === 'worker' || currentUser.role === 'admin') {
        document.getElementById('loans-tab').style.display = 'block';
        document.getElementById('add-book-btn').style.display = 'block';
        document.getElementById('books-actions-col').style.display = 'table-cell';
    }
    
    if (currentUser.role === 'admin') {
        document.getElementById('users-tab').style.display = 'block';
    }
    
    setupEventListeners();
}

function setupEventListeners() {
    // Logout
    document.getElementById('logout-btn').addEventListener('click', () => {
        localStorage.clear();
        window.location.href = 'index.html';
    });
    
    // Tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            tab.classList.add('active');
            document.getElementById(`${tab.dataset.tab}-tab-content`).classList.add('active');
            
            if (tab.dataset.tab === 'books') loadBooks();
            if (tab.dataset.tab === 'loans') loadLoans();
            if (tab.dataset.tab === 'users') loadUsers();
        });
    });
    
    // Search
    let searchTimeout;
    document.getElementById('search-books').addEventListener('input', (e) => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadBooks(e.target.value), 300);
    });
    
    // Modals
    document.querySelectorAll('.close').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal').style.display = 'none';
        });
    });
    
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none';
        }
    });
    
    // Forms
    document.getElementById('add-book-btn')?.addEventListener('click', () => openBookModal());
    document.getElementById('book-form').addEventListener('submit', handleBookSubmit);
    document.getElementById('add-loan-btn')?.addEventListener('click', () => openLoanModal());
    document.getElementById('loan-form').addEventListener('submit', handleLoanSubmit);
    document.getElementById('add-user-btn')?.addEventListener('click', () => openUserModal());
    document.getElementById('user-form').addEventListener('submit', handleUserSubmit);
}

// Books
async function loadBooks(search = '') {
    try {
        const books = await API.getBooks(search);
        const tbody = document.querySelector('#books-table tbody');
        
        tbody.innerHTML = books.map(book => `
            <tr>
                <td>${book.title}</td>
                <td>${book.author}</td>
                <td>${book.isbn || 'N/A'}</td>
                <td>${book.available}</td>
                <td>${book.quantity}</td>
                ${currentUser.role !== 'guest' ? `
                    <td>
                        <button class="btn btn-small btn-secondary" onclick="openBookModal(${book.id})">Edit</button>
                        <button class="btn btn-small btn-danger" onclick="deleteBook(${book.id})">Delete</button>
                    </td>
                ` : ''}
            </tr>
        `).join('');
    } catch (error) {
        alert('Failed to load books: ' + error.message);
    }
}

function openBookModal(bookId = null) {
    const modal = document.getElementById('book-modal');
    const form = document.getElementById('book-form');
    
    if (bookId) {
        document.getElementById('book-modal-title').textContent = 'Edit Book';
        // Load book data
        const row = event.target.closest('tr');
        const cells = row.querySelectorAll('td');
        document.getElementById('book-id').value = bookId;
        document.getElementById('book-title').value = cells[0].textContent;
        document.getElementById('book-author').value = cells[1].textContent;
        document.getElementById('book-isbn').value = cells[2].textContent === 'N/A' ? '' : cells[2].textContent;
        document.getElementById('book-available').value = cells[3].textContent;
        document.getElementById('book-quantity').value = cells[4].textContent;
    } else {
        document.getElementById('book-modal-title').textContent = 'Add Book';
        form.reset();
        document.getElementById('book-id').value = '';
        document.getElementById('book-available').value = '';
    }
    
    modal.style.display = 'block';
}

async function handleBookSubmit(e) {
    e.preventDefault();
    
    const bookId = document.getElementById('book-id').value;
    const book = {
        title: document.getElementById('book-title').value,
        author: document.getElementById('book-author').value,
        isbn: document.getElementById('book-isbn').value,
        quantity: parseInt(document.getElementById('book-quantity').value)
    };
    
    if (bookId) {
        book.available = parseInt(document.getElementById('book-available').value);
    } else {
        book.available = book.quantity;
    }
    
    try {
        if (bookId) {
            await API.updateBook(bookId, book);
        } else {
            await API.createBook(book);
        }
        
        closeModal('book-modal');
        loadBooks();
    } catch (error) {
        alert('Failed to save book: ' + error.message);
    }
}

async function deleteBook(id) {
    if (!confirm('Are you sure you want to delete this book?')) return;
    
    try {
        await API.deleteBook(id);
        loadBooks();
    } catch (error) {
        alert('Failed to delete book: ' + error.message);
    }
}

// Loans
async function loadLoans() {
    try {
        const loans = await API.getLoans();
        const tbody = document.querySelector('#loans-table tbody');
        
        tbody.innerHTML = loans.map(loan => {
            const isOverdue = !loan.returned_date && new Date(loan.due_date) < new Date();
            const status = loan.returned_date ? 'Returned' : (isOverdue ? 'Overdue' : 'Active');
            const badgeClass = loan.returned_date ? 'success' : (isOverdue ? 'danger' : 'warning');
            
            return `
                <tr>
                    <td>${loan.title}</td>
                    <td>${loan.username}</td>
                    <td>${new Date(loan.borrowed_date).toLocaleDateString()}</td>
                    <td>${new Date(loan.due_date).toLocaleDateString()}</td>
                    <td><span class="badge badge-${badgeClass}">${status}</span></td>
                    <td>
                        ${!loan.returned_date ? 
                            `<button class="btn btn-small btn-success" onclick="returnLoan(${loan.id})">Return</button>` : 
                            '-'}
                    </td>
                </tr>
            `;
        }).join('');
    } catch (error) {
        alert('Failed to load loans: ' + error.message);
    }
}

async function openLoanModal() {
    const modal = document.getElementById('loan-modal');
    
    try {
        const [books, users] = await Promise.all([API.getBooks(), API.getUsers()]);
        
        const bookSelect = document.getElementById('loan-book');
        bookSelect.innerHTML = books
            .filter(b => b.available > 0)
            .map(b => `<option value="${b.id}">${b.title} (Available: ${b.available})</option>`)
            .join('');
        
        const userSelect = document.getElementById('loan-user');
        userSelect.innerHTML = users.map(u => `<option value="${u.id}">${u.username}</option>`).join('');
        
        // Set default due date to 2 weeks from now
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 14);
        document.getElementById('loan-due-date').value = dueDate.toISOString().split('T')[0];
        
        modal.style.display = 'block';
    } catch (error) {
        alert('Failed to open loan form: ' + error.message);
    }
}

async function handleLoanSubmit(e) {
    e.preventDefault();
    
    const loan = {
        book_id: parseInt(document.getElementById('loan-book').value),
        user_id: parseInt(document.getElementById('loan-user').value),
        due_date: document.getElementById('loan-due-date').value
    };
    
    try {
        await API.createLoan(loan);
        closeModal('loan-modal');
        loadLoans();
        loadBooks(); // Refresh books to update availability
    } catch (error) {
        alert('Failed to create loan: ' + error.message);
    }
}

async function returnLoan(id) {
    if (!confirm('Mark this book as returned?')) return;
    
    try {
        await API.returnLoan(id);
        loadLoans();
        loadBooks(); // Refresh books to update availability
    } catch (error) {
        alert('Failed to return book: ' + error.message);
    }
}

// Users
async function loadUsers() {
    try {
        const users = await API.getUsers();
        const tbody = document.querySelector('#users-table tbody');
        
        tbody.innerHTML = users.map(user => `
            <tr>
                <td>${user.username}</td>
                <td>${user.role}</td>
                <td>${new Date(user.created_at).toLocaleDateString()}</td>
                <td>
                    ${user.id !== currentUser.id ? 
                        `<button class="btn btn-small btn-danger" onclick="deleteUser(${user.id})">Delete</button>` : 
                        '-'}
                </td>
            </tr>
        `).join('');
    } catch (error) {
        alert('Failed to load users: ' + error.message);
    }
}

function openUserModal() {
    document.getElementById('user-modal').style.display = 'block';
    document.getElementById('user-form').reset();
}

async function handleUserSubmit(e) {
    e.preventDefault();
    
    const user = {
        username: document.getElementById('user-username').value,
        password: document.getElementById('user-password').value,
        role: document.getElementById('user-role').value
    };
    
    try {
        await API.createUser(user);
        closeModal('user-modal');
        loadUsers();
    } catch (error) {
        alert('Failed to create user: ' + error.message);
    }
}

async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    
    try {
        await API.deleteUser(id);
        loadUsers();
    } catch (error) {
        alert('Failed to delete user: ' + error.message);
    }
}

// Utility
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}