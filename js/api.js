const API_BASE = 'http://localhost/ManageYourLibrary/backend/api';

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
            
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                console.error('Server returned non-JSON response:', text);
                throw new Error('Server configuration error. Check console for details.');
            }
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Request failed');
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
        return this.request('/books.php', {
            method: 'PUT',
            body: JSON.stringify({ ...book, id }) // Send ID in body
        });
    }

    static async deleteBook(id) {
        return this.request('/books.php', {
            method: 'DELETE',
            body: JSON.stringify({ id }) // Send ID in body
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
        return this.request('/loans.php', {
            method: 'PUT',
            body: JSON.stringify({ id }) // Send ID in body instead of URL
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
        return this.request('/users.php', {
            method: 'DELETE',
            body: JSON.stringify({ id }) // Send ID in body
        });
    }
}