import { useState } from 'react';

function Sidebar({ pagina, cambiarPagina }) {
    const [abierto, setAbierto] = useState(true);

    const navegar = (pagina) => {
        cambiarPagina(pagina);
        setAbierto(false);
    };

    return (
        <>
            <button
                className="hamburger"
                onClick={() => setAbierto(!abierto)}
            >
                ☰
            </button>

            {abierto && (
                <div
                    className="sidebar-overlay"
                    onClick={() => setAbierto(false)}
                />
            )}

            <aside className={`sidebar ${abierto ? 'sidebar-open' : ''}`}>

                <div className="sidebar-header">
                    <div className="logo">
                        P
                    </div>

                    <div>
                        <h2>Polaris Soft</h2>
                        <span>Administración</span>
                    </div>
                </div>

                <nav>

                    <p className="menu-title">
                        PRINCIPAL
                    </p>

                    <button
                        className={`menu-item ${pagina === 'dashboard' ? 'active' : ''}`}
                        onClick={() => navegar('dashboard')}
                    >
                        <span>⌂</span>
                        Dashboard
                    </button>

                    <p className="menu-title">
                        GESTIÓN ACADÉMICA
                    </p>

                    <button
                        className={`menu-item ${pagina === 'estudiantes' ? 'active' : ''}`}
                        onClick={() => navegar('estudiantes')}
                    >
                        <span>👨‍🎓</span>
                        Estudiantes
                    </button>

                    <button
                        className={`menu-item ${pagina === 'examenes' ? 'active' : ''}`}
                        onClick={() => navegar('examenes')}
                    >
                        <span>📝</span>
                        Exámenes
                    </button>

                </nav>

                <div className="sidebar-footer">
                    <div className="user-avatar">
                        A
                    </div>

                    <div>
                        <strong>Administrador</strong>
                        <small>Sistema</small>
                    </div>
                </div>

            </aside>
        </>
    );
}

export default Sidebar;