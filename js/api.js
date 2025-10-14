const API_BASE = 'http://localhost/library-app/backend/api';

class API {
    static async request(endpoint, options = {}) {
        const token = localStorage.getItem('token');
        
        const config = {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...(token && { 'Authorization': `Bearer ${token}` }),
                ...options.headers
            }
        };

        try {
            const response = await fetch(`${API_BASE}${endpoint}`, config);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }

    static async login(username, password) {
        return this.request('/auth.php', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });
    }

    static async getBooks(search = '') {
        return this.request(`/books.php?search=${encodeURIComponent(search)}`);
    }

    static async createBook(book) {
        return this.request('/books.php', {
            method: 'POST',
            body: JSON.stringify(book)
        });
    }

    static async updateBook(id, book) {
        return this.request(`/books.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(book)
        });
    }

    static async deleteBook(id) {
        return this.request(`/books.php?id=${id}`, {
            method: 'DELETE'
        });
    }

    static async getLoans() {
        return this.request('/loans.php');
    }

    static async createLoan(loan) {
        return this.request('/loans.php', {
            method: 'POST',
            body: JSON.stringify(loan)
        });
    }

    static async returnLoan(id) {
        return this.request(`/loans.php?id=${id}`, {
            method: 'PUT'
        });
    }

    static async getUsers() {
        return this.request('/users.php');
    }

    static async createUser(user) {
        return this.request('/users.php', {
            method: 'POST',
            body: JSON.stringify(user)
        });
    }

    static async deleteUser(id) {
        return this.request(`/users.php?id=${id}`, {
            method: 'DELETE'
        });
    }
}