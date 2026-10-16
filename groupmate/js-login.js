document.getElementById('loginForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const usernameInput = document.getElementById('username');
  const passwordInput = document.getElementById('password');
  const roleInput = document.getElementById('role');

  const username = usernameInput.value.trim();
  const password = passwordInput.value;
  const role = roleInput.value;
  let valid = true;

  usernameInput.classList.remove('is-invalid');
  passwordInput.classList.remove('is-invalid');
  roleInput.classList.remove('is-invalid');

  if (!username) {
    usernameInput.classList.add('is-invalid');
    document.getElementById('usernameError').textContent = 'Username is required.';
    valid = false;
  }
  if (password.length < 3) {
    passwordInput.classList.add('is-invalid');
    document.getElementById('passwordError').textContent = 'Password must be at least 3 characters.';
    valid = false;
  }
  if (!role) {
    roleInput.classList.add('is-invalid');
    valid = false;
  }

  if (valid) {
    try {
      const response = await fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password, role })
      });
      const data = await response.json();

      if (data.success) {
        window.location.href = data.redirect_url;
      } else {
        usernameInput.classList.add('is-invalid');
        document.getElementById('usernameError').textContent = data.message || 'Login failed.';
      }
    } catch (err) {
      console.error('Login error:', err);
      alert('Error connecting to authentication API.');
    }
  }
});





