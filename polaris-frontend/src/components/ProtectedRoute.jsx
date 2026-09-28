import { Navigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';

export default function ProtectedRoute({
    children,
    roles = [],
}) {
    const { user, loading } = useAuth();

    if (loading) {
        return <div>Cargando...</div>;
    }

    if (!user) {
        return <Navigate to="/login" replace />;
    }

    if (
        roles.length > 0 &&
        !roles.includes(user.rol.nombre)
    ) {
        return <Navigate to="/no-autorizado" replace />;
    }

    return children;
}