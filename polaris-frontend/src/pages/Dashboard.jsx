function Dashboard() {

    return (
        <div>

            <div className="page-heading">

                <div>
                    <h2>Dashboard</h2>

                    <p>
                        Administración del sistema académico
                    </p>
                </div>

            </div>

            <div className="stats-grid">

                <div className="stat-card">

                    <div className="stat-icon blue">
                        👨‍🎓
                    </div>

                    <div>
                        <span>Estudiantes</span>
                        <strong>HU-001</strong>
                    </div>

                </div>

                <div className="stat-card">

                    <div className="stat-icon purple">
                        📝
                    </div>

                    <div>
                        <span>Exámenes</span>
                        <strong>HU-003</strong>
                    </div>

                </div>

            </div>

            <div className="welcome-card">

                <div>
                    <h3>Bienvenido a Polaris Soft</h3>

                    <p>
                        Desde el menú lateral puedes administrar
                        los estudiantes y los exámenes del sistema.
                    </p>
                </div>

            </div>

        </div>
    );
}

export default Dashboard;