let currentUser = null;

// Check authentication
function checkAuth() {
    const token = localStorage.getItem('token');
    const user = localStorage.getItem('user');
    // Debug log for mobile issues
    console.log('Auth token:', token);
    console.log('Auth user:', user);
    if (!token || !user) {
        alert('Missing token or user info. Please login again.');
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

    // Remove existing event listeners before adding a new one
    const userForm = document.getElementById('user-form');
    userForm.removeEventListener('submit', handleUserSubmit);
    userForm.addEventListener('submit', handleUserSubmit);
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
        console.error('loadBooks error:', error); // Debug log
        // Show error in UI for mobile users
        const tbody = document.querySelector('#books-table tbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="6">Error: ${error.message}</td></tr>`;
        }
        // If unauthorized, force logout
        if (error.message.includes('Unauthorized') || error.message.includes('Forbidden')) {
            alert('Session expired or unauthorized. Please login again.');
            localStorage.clear();
            window.location.href = 'index.html';
        } else {
            alert('Failed to load books: ' + error.message);
        }
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

// QR Scanner for Loans
async function startBookQRScannerForLoan() {
    const qrScanner = document.getElementById('book-qr-scanner-loan');
    qrScanner.style.display = 'block';

    const html5QrCode = new Html5Qrcode("book-qr-reader-loan");
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        async (decodedText) => {
            console.log("Scanned Book Code:", decodedText);
            await loadBookForLoan(decodedText);
            html5QrCode.stop();
            qrScanner.style.display = 'none';
        }
    ).catch((err) => {
        console.error("QR Scanner Error:", err);
        alert("Failed to start QR scanner. Please check camera permissions and try again.");
    });
}

async function loadBookForLoan(bookCode) {
    try {
        const res = await fetch(`api/get_book_by_code.php?code=${bookCode}`);
        const book = await res.json();
        if (book && book.id) {
            document.querySelector('#loan-book').value = book.id;
            alert(`Book Found: ${book.title} by ${book.author}`);
        } else {
            alert("Book not found!");
        }
    } catch (error) {
        console.error("Error loading book by code:", error);
        alert("Failed to load book details. Please try again.");
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
document.getElementById('user-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const user = {
        first_name: document.getElementById('user-first-name').value,
        last_name: document.getElementById('user-last-name').value,
        username: document.getElementById('user-username').value,
        password: document.getElementById('user-password').value,
        role: document.getElementById('user-role').value
    };

    try {
        const token = localStorage.getItem('token');
        const response = await fetch('backend/api/users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(user)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to create user');
        }

        alert('User created successfully!');
        closeModal('user-modal');
        loadUsers(); // Refresh the users list
    } catch (error) {
        console.error('Failed to create user:', error);
        alert('Failed to create user: ' + error.message);
    }
});
function openUserModal() {
    document.getElementById('user-modal').style.display = 'block';
    document.getElementById('user-form').reset();
}

async function handleUserSubmit(e) {
    e.preventDefault();

    const user = {
        first_name: document.getElementById('user-first-name').value,
        last_name: document.getElementById('user-last-name').value,
        username: document.getElementById('user-username').value,
        password: document.getElementById('user-password').value,
        role: document.getElementById('user-role').value
    };

    try {
        const token = localStorage.getItem('token');
        const response = await fetch('backend/api/users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(user)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Failed to create user');
        }

        const data = await response.json();
        console.log('User created successfully:', data); // Debugging
        alert('User created successfully!');
        closeModal('user-modal');
        loadUsers(); // Refresh the users list
    } catch (error) {
        console.error('Failed to create user:', error);
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

const API = {
    async createBook(book) {
        const token = localStorage.getItem('token');
        const response = await fetch('backend/api/books.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(book)
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to save book');
        }
        return data;
    },
    async updateBook(bookId, book) {
        const token = localStorage.getItem('token');
        const payload = { ...book, id: bookId, _method: 'PUT' };
        const response = await fetch('backend/api/books.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(payload)
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to update book');
        }
        return data;
    },
    async deleteBook(bookId) {
        const token = localStorage.getItem('token');
        const payload = { id: bookId, _method: 'DELETE' };
        const response = await fetch('backend/api/books.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(payload)
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to delete book');
        }
        return data;
    },
    async getBooks(search = '') {
        const token = localStorage.getItem('token');
        const response = await fetch(`backend/api/books.php?search=${encodeURIComponent(search)}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : [];
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to load books');
        }
        return data;
    },
    async getLoans() {
        const token = localStorage.getItem('token');
        const response = await fetch('backend/api/loans.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : [];
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to load loans');
        }
        return data;
    },
    async getUsers() {
        const token = localStorage.getItem('token');
        const response = await fetch('backend/api/users.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : [];
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to load users');
        }
        return data;
    },
    async createLoan(loan) {
        const token = localStorage.getItem('token');
        const response = await fetch('backend/api/loans.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(loan)
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to create loan');
        }
        return data;
    },
    async returnLoan(loanId) {
        const token = localStorage.getItem('token');
        const response = await fetch('backend/api/loans.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ id: loanId, _method: 'PUT' })
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to return loan');
        }
        return data;
    },
    async deleteUser(userId) {
        const token = localStorage.getItem('token');
        const response = await fetch('backend/api/users.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ id: userId })
        });
        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (e) {
            throw new Error('Invalid server response');
        }
        if (!response.ok) {
            throw new Error(data.error || 'Failed to delete user');
        }
        return data;
    }
};

async function startQRScanner() {
    const qrScanner = document.getElementById('qr-scanner');
    const qrReader = document.getElementById('qr-reader');
    qrScanner.classList.add('show');
    // Clear previous QR reader content
    if (qrReader) qrReader.innerHTML = '';

    // Prevent multiple instances
    if (window._memberQrCodeScanner) {
        await window._memberQrCodeScanner.stop().catch(() => {});
        window._memberQrCodeScanner = null;
    }

    const html5QrCode = new Html5Qrcode("qr-reader");
    html5QrCode.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: 250 },
        async (decodedText) => {
            console.log("Scanned Member Card:", decodedText);
            alert(`Member Card Scanned: ${decodedText}`);
            await html5QrCode.stop();
            qrScanner.classList.remove('show');
            window._memberQrCodeScanner = null;
        }
    ).catch((err) => {
        console.error("QR Scanner Error:", err);
        alert("Failed to start QR scanner. Please check camera permissions and try again.");
        qrScanner.classList.remove('show');
    });

    window._memberQrCodeScanner = html5QrCode;
}

function stopQRScanner() {
    const qrScanner = document.getElementById('qr-scanner');
    qrScanner.classList.remove('show');
    if (window._memberQrCodeScanner) {
        window._memberQrCodeScanner.stop().catch(() => {});
        window._memberQrCodeScanner = null;
    }
}

// At the end of the file, export the QR scanner functions to window
window.startQRScanner = startQRScanner;
window.stopQRScanner = stopQRScanner;