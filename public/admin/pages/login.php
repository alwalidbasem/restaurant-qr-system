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

  <div id="loginForm">
    <div class="mb-3 auth-field">
      <label class="form-label" for="loginUsername">Username</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-person"></i></span>
        <input type="text" class="form-control" id="loginUsername" name="username" placeholder="admin@savora.com" autocomplete="username" required>
      </div>
    </div>

    <div class="mb-3 auth-field">
      <label class="form-label" for="loginRestaurantCode">Restaurant Code</label>
      <div class="input-group">
        <span class="input-group-text bg-white"><i class="bi bi-building"></i></span>
        <input type="text" class="form-control" id="loginRestaurantCode" name="restaurant_code" placeholder="Restaurant code" autocomplete="organization" required>
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

    <button class="btn btn-primary auth-submit" id="loginSubmit">
      <span class="spinner-border spinner-border-sm d-none" id="loginSpinner"></span>
      <span id="loginSubmitText">Sign In</span>
    </button>
  </div>

  <p class="auth-footer">&copy; 2026 Savora Restaurant</p>
</div>
