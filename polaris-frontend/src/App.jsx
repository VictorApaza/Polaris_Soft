import { BrowserRouter, Routes, Route } from 'react-router-dom';

import { AuthProvider } from './context/AuthContext';

import Login from './pages/login';

import AdminDashboard from './pages/admin/AdminDashboard';
import DocenteDashboard from './pages/docente/DocenteDashboard';
import EstudianteDashboard from './pages/estudiante/EstudianteDashboard';

import ProtectedRoute from './components/ProtectedRoute';

function NoAutorizado() {
    return (
        <div>
            <h1>403</h1>
            <p>No tienes permisos para acceder a este módulo.</p>
        </div>
    );
}

export default function App() {
    return (
        <AuthProvider>
            <BrowserRouter>

                <Routes>

                    <Route
                        path="/login"
                        element={<Login />}
                    />

                    <Route
                        path="/admin"
                        element={
                            <ProtectedRoute
                                roles={['Administrador']}
                            >
                                <AdminDashboard />
                            </ProtectedRoute>
                        }
                    />

                    <Route
                        path="/docente"
                        element={
                            <ProtectedRoute
                                roles={['Docente']}
                            >
                                <DocenteDashboard />
                            </ProtectedRoute>
                        }
                    />

                    <Route
                        path="/estudiante"
                        element={
                            <ProtectedRoute
                                roles={['Estudiante']}
                            >
                                <EstudianteDashboard />
                            </ProtectedRoute>
                        }
                    />

                    <Route
                        path="/no-autorizado"
                        element={<NoAutorizado />}
                    />

                    <Route
                        path="*"
                        element={<Login />}
                    />

                </Routes>

            </BrowserRouter>
        </AuthProvider>
    );
}