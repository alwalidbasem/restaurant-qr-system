<?php
// Login page (frontend only). No backend authentication is wired up yet.
?>

<div class="auth-card">
  <div class="auth-brand">
    <div class="brand-mark"><i class="bi bi-egg-fried"></i></div>
    <div class="brand-name"><i class="bi bi-record-fill"></i> Savora <span class="fw-light">Admin</span></div>
  </div>

  <h1 class="auth-heading h3">Welcome back</h1>
  <p class="auth-sub">Sign in to manage your restaurant.</p>

  <div class="alert alert-warning alert-dismissible fade auth-alert" id="authAlert" role="alert">
    <i class="bi bi-info-circle"></i>
    <span id="authAlertText">Frontend preview only — authentication will be connected later.</span>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>

  <form id="loginForm" novalidate>
    <div class="mb-3 auth-field">
      <label class="form-label" for="loginUsername">Username or Email</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
        <input type="text" class="form-control" id="loginUsername" name="username" placeholder="admin@savora.com" autocomplete="username" required>
      </div>
    </div>

    <div class="mb-3 auth-field">
      <label class="form-label" for="loginPassword">Password</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
        <input type="password" class="form-control" id="loginPassword" name="password" placeholder="••••••••" autocomplete="current-password" required>
        <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Show password">
          <i class="bi bi-eye" id="togglePasswordIcon"></i>
        </button>
      </div>
    </div>

    <div class="auth-options mb-3">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
        <label class="form-check-label" for="rememberMe">Remember me</label>
      </div>
      <a href="#" class="text-decoration-none" style="color:var(--primary);">Forgot password?</a>
    </div>

    <button class="btn btn-primary auth-submit" type="submit" id="loginSubmit">
      <span class="spinner-border spinner-border-sm d-none" id="loginSpinner"></span>
      <span id="loginSubmitText">Sign In</span>
    </button>
  </form>

  <p class="auth-footer">&copy; 2026 Savora Restaurant</p>
</div>

<script>
(function () {
  var form = document.getElementById('loginForm');
  var submitBtn = document.getElementById('loginSubmit');
  var spinner = document.getElementById('loginSpinner');
  var submitText = document.getElementById('loginSubmitText');
  var alertBox = document.getElementById('authAlert');
  var alertText = document.getElementById('authAlertText');
  var toggleBtn = document.getElementById('togglePassword');
  var passwordInput = document.getElementById('loginPassword');
  var toggleIcon = document.getElementById('togglePasswordIcon');

  // Show / hide password
  toggleBtn.addEventListener('click', function () {
    var isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    toggleIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
  });

  // Frontend-only submit: simulate a short delay, then show an info alert.
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    e.stopPropagation();

    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      return;
    }

    submitBtn.disabled = true;
    spinner.classList.remove('d-none');
    submitText.textContent = 'Signing in...';

    window.setTimeout(function () {
      submitBtn.disabled = false;
      spinner.classList.add('d-none');
      submitText.textContent = 'Sign In';

      alertText.textContent = 'Frontend preview only — the login endpoint is not wired up yet.';
      alertBox.classList.add('show');
    }, 900);
  });
})();
</script>
