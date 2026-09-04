// POS Accounting System - Login page client-side logic
// NOTE: Static demo only. Replace with real backend auth later once decided what it is  or somethin.

document.getElementById('loginForm').addEventListener('submit', function (e) {
  e.preventDefault();

  var username = document.getElementById('username').value.trim();
  var password = document.getElementById('password').value;
  var role = document.getElementById('role').value;
  var valid = true;

  // Reset errors
  document.getElementById('username').classList.remove('is-invalid');
  document.getElementById('password').classList.remove('is-invalid');
  document.getElementById('role').classList.remove('is-invalid');

  // Validate
  if (!username) {
    document.getElementById('username').classList.add('is-invalid');
    document.getElementById('usernameError').textContent = 'Username is required.';
    valid = false;
  }
  if (password.length < 3) {
    document.getElementById('password').classList.add('is-invalid');
    document.getElementById('passwordError').textContent = 'Password must be at least 3 characters.';
    valid = false;
  }
  if (!role) {
    document.getElementById('role').classList.add('is-invalid');
    valid = false;
  }

  if (valid) {
    // Route to the appropriate dashboard shell based on role
    var pages = {
      admin:   'pages/admin-dashboard.html',
      manager: 'pages/manager-dashboard.html',
      cashier: 'pages/cashier-dashboard.html'
    };
    window.location.href = pages[role];
  }
});
