import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function Login() {
    const navigate = useNavigate();
    const { login } = useAuth();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();

        setError('');
        setLoading(true);

        try {
            const user = await login(email, password);

            switch (user.rol.nombre) {
                case 'Administrador':
                    navigate('/admin');
                    break;

                case 'Docente':
                    navigate('/docente');
                    break;

                case 'Estudiante':
                    navigate('/estudiante');
                    break;

                default:
                    setError('El usuario no tiene un rol válido.');
            }
        } catch (error) {
            if (error.response?.status === 401) {
                setError('Correo o contraseña incorrectos.');
            } else if (error.response?.status === 403) {
                setError(
                    error.response.data.message ||
                    'El usuario no tiene acceso al sistema.'
                );
            } else {
                setError('No se pudo conectar con el servidor.');
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="login-page">
            <div className="login-container">

                <div className="login-header">
                    <h1>Polaris Soft</h1>
                    <p>Gestión académica</p>
                </div>

                <form onSubmit={handleSubmit}>

                    <div className="form-group">
                        <label>Correo electrónico</label>

                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="correo@ejemplo.com"
                            required
                        />
                    </div>

                    <div className="form-group">
                        <label>Contraseña</label>

                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Ingrese su contraseña"
                            required
                        />
                    </div>

                    {error && (
                        <div className="login-error">
                            {error}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={loading}
                    >
                        {loading ? 'Ingresando...' : 'Iniciar sesión'}
                    </button>

                </form>

            </div>
        </div>
    );
}