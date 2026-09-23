const passwordToggle = document.querySelector('[data-password-toggle]');

if (passwordToggle) {
    passwordToggle.addEventListener('click', () => {
        const password = document.querySelector('#password');
        const isPassword = password.type === 'password';

        password.type = isPassword ? 'text' : 'password';
        passwordToggle.setAttribute(
            'aria-label',
            isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña',
        );
    });
}
