import { useAuth } from '../../context/AuthContext';

export default function AdminDashboard() {
    const { user, logout } = useAuth();

    return (
        <div>
            <h1>Dashboard de Administrador</h1>

            <p>
                Bienvenido, {user.name}
            </p>

            <p>
                Rol: {user.rol.nombre}
            </p>

            <button onClick={logout}>
                Cerrar sesión
            </button>
        </div>
    );
}