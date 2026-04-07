const passwordInput = document.getElementById('password') || document.getElementById('new_password');
const confirmInput = document.getElementById('confirm_password');
const strengthText = document.getElementById('strengthText');
const strengthBar = document.getElementById('strengthBar');
const adviceList = document.getElementById('strengthAdvice');
const form = document.getElementById('registerForm') || document.getElementById('changePasswordForm');
const usernameInput = document.getElementById('username');

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const button = document.querySelector(`[data-toggle-id="${inputId}"]`);
    if (!input || !button) return;
    
    if (input.type === 'password') {
        input.type = 'text';
        button.innerHTML = '👁️‍🗨️ Hide';
        button.classList.add('active');
    } else {
        input.type = 'password';
        button.innerHTML = '👁️ Show';
        button.classList.remove('active');
    }
}

function estimateStrength(password, username) {
    let score = 0;
    const advice = [];
    if (password.length >= 12) {
        score += 2;
    } else if (password.length >= 10) {
        score += 1;
        advice.push('Use at least 12 characters.');
    } else {
        advice.push('Password needs to be longer.');
    }
    if (/[A-Z]/.test(password)) score++;
    else advice.push('Add uppercase letters.');
    if (/[a-z]/.test(password)) score++;
    else advice.push('Add lowercase letters.');
    if (/[0-9]/.test(password)) score++;
    else advice.push('Add digits.');
    if (/[!@#$%^&*()_+\-=[\]{};:'"\\|,.<>\/?]/.test(password)) score++;
    else advice.push('Add special characters.');
    if (/\s/.test(password)) advice.push('Avoid spaces.');
    if (username && password.toLowerCase().includes(username.toLowerCase())) {
        advice.push('Do not include the username in the password.');
    }
    if (new Set(password).size >= 6) score++;
    const label = score <= 1 ? 'Very weak' : score === 2 ? 'Weak' : score === 3 ? 'Moderate' : score === 4 ? 'Strong' : 'Very strong';
    return {score: Math.min(score, 6), label, advice};
}

function updateStrength() {
    const username = usernameInput ? usernameInput.value : '';
    const password = passwordInput.value;
    const result = estimateStrength(password, username);
    const percent = (result.score / 6) * 100;
    strengthBar.style.width = `${percent}%`;
    strengthBar.className = 'progress-bar';
    if (result.score <= 1) strengthBar.classList.add('bg-danger');
    else if (result.score <= 3) strengthBar.classList.add('bg-warning');
    else if (result.score === 4) strengthBar.classList.add('bg-info');
    else strengthBar.classList.add('bg-success');
    strengthText.textContent = result.label;
    adviceList.innerHTML = '';
    result.advice.slice(0, 3).forEach(item => {
        const li = document.createElement('li');
        li.textContent = item;
        adviceList.appendChild(li);
    });
}

if (passwordInput && strengthText && strengthBar && adviceList) {
    passwordInput.addEventListener('input', updateStrength);
}
if (form && passwordInput && confirmInput) {
    form.addEventListener('submit', (event) => {
        if (passwordInput.value !== confirmInput.value) {
            event.preventDefault();
            alert('Password and confirmation must match.');
        }
    });
}
