// POS Accounting System - Login page client-side logic
// POSTs to api/login.php (same origin, served by Apache at /pos/).
// Reply contract (see NOTES.md): { success: true, role, full_name }
//   -> role comes from the DB; route to pages/<role>-dashboard.php
// The account-type dropdown in index.html is no longer required.

document.getElementById('loginForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const usernameInput = document.getElementById('username');
  const passwordInput = document.getElementById('password');
  const roleInput = document.getElementById('role'); // may be removed (task 4)

  const username = usernameInput.value.trim();
  const password = passwordInput.value;

  // Reset errors
  usernameInput.classList.remove('is-invalid');
  passwordInput.classList.remove('is-invalid');
  if (roleInput) roleInput.classList.remove('is-invalid');

  // Validate
  let valid = true;
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
  if (!valid) return;

  try {
    const response = await fetch('api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username: username, password: password })
    });
    const data = await response.json();

    if (data.success) {
      window.location.href = 'pages/' + data.role + '-dashboard.php';
    } else {
      usernameInput.classList.add('is-invalid');
      document.getElementById('usernameError').textContent = data.message || 'Login failed.';
    }
  } catch (err) {
    console.error('Login error:', err);
    alert('Could not reach the authentication API. Is Apache running at /pos/?');
  }
});
