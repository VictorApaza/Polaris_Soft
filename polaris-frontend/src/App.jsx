import { useState } from 'react';

import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Estudiantes from './pages/estudiantes/Estudiantes';
import Examenes from './pages/examenes/Examenes';

function App() {

    const [pagina, setPagina] = useState('dashboard');

    const renderizarPagina = () => {

        switch (pagina) {

            case 'estudiantes':
                return <Estudiantes />;

            case 'examenes':
                return <Examenes />;

            default:
                return <Dashboard />;
        }
    };

    return (
        <Layout
            pagina={pagina}
            cambiarPagina={setPagina}
        >
            {renderizarPagina()}
        </Layout>
    );
}

export default App;