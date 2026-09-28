import Sidebar from './Sidebar';

function Layout({ pagina, cambiarPagina, children }) {

    return (
        <div className="app">

            <Sidebar
                pagina={pagina}
                cambiarPagina={cambiarPagina}
            />

            <main className="main-content">

                <header className="topbar">

                    <div>
                        <span className="topbar-label">
                            POLARIS SOFT
                        </span>

                        <h1>
                            Sistema de Gestión Académica
                        </h1>
                    </div>

                </header>

                <section className="page-content">
                    {children}
                </section>

            </main>

        </div>
    );
}

export default Layout;