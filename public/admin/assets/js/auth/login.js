(function () {
  var submitBtn = document.getElementById('loginSubmit');
  var spinner = document.getElementById('loginSpinner');
  var submitText = document.getElementById('loginSubmitText');
  var alertBox = document.getElementById('authAlert');
  var alertText = document.getElementById('authAlertText');
  var toggleBtn = document.getElementById('togglePassword');
  var usernameInput = document.getElementById('loginUsername');
  var restaurantCodeInput = document.getElementById('loginRestaurantCode');
  var passwordInput = document.getElementById('loginPassword');
  var toggleIcon = document.getElementById('togglePasswordIcon');

  if (!submitBtn || !usernameInput || !passwordInput || !restaurantCodeInput) return;

  function setLoading(isLoading) {
    submitBtn.disabled = isLoading;
    spinner.classList.toggle('d-none', !isLoading);
    submitText.textContent = isLoading ? 'Signing in...' : 'Sign In';
  }

  function showError(message) {
    alertText.textContent = message;
    alertBox.classList.add('show');
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var show = passwordInput.type === 'password';
      passwordInput.type = show ? 'text' : 'password';
      toggleIcon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  }

  [usernameInput, restaurantCodeInput, passwordInput].forEach(function (input) {
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') submitBtn.click();
    });
  });

  submitBtn.addEventListener('click', function () {
    var username = usernameInput.value.trim();
    var restaurantCode = restaurantCodeInput.value.trim();
    var password = passwordInput.value;

    if (username === '' || restaurantCode === '' || password === '') {
      showError('Username, restaurant code, and password are required.');
      return;
    }

    setLoading(true);

    var appBase = window.location.pathname.split('/admin')[0] || '';

    fetch(appBase + '/api/auth', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username: username,
        restaurant_code: restaurantCode,
        password: password
      })
    })
      .then(function (response) {
        return response.json().then(function (payload) {
          if (!response.ok || payload.success === false) throw payload;
          return payload;
        });
      })
      .then(function () {
        window.location.href = '?page=dashboard';
      })
      .catch(function (error) {
        setLoading(false);
        showError(error.message || 'Unable to sign in.');
      });
  });
})();
