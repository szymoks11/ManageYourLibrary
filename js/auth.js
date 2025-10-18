document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorMessage = document.getElementById('error-message');
    
    try {
        const response = await fetch('backend/api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ username, password })
        });
        
        const responseText = await response.text(); // Log the full response
        console.log('Response:', responseText);
        
        const data = JSON.parse(responseText); // Parse the response
        
        if (!response.ok || !data.token) { // Check if the response is not OK or token is missing
            throw new Error(data.error || 'Login failed');
        }
        
        // Save token and user details to localStorage
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        
        // Redirect to dashboard
        window.location.href = 'dashboard.html';
    } catch (error) {
        console.error('Error during login:', error);
        errorMessage.textContent = error.message;
        errorMessage.style.display = 'block';
    }
});