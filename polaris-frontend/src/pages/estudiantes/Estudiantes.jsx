import { useEffect, useState } from 'react';
import api from '../../api/api';

function Estudiantes() {

    const [estudiantes, setEstudiantes] = useState([]);
    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [editando, setEditando] = useState(null);
    const [cargando, setCargando] = useState(true);

    const [formulario, setFormulario] = useState({
        codigo_universitario: '',
        documento_identidad: '',
        nombres: '',
        apellidos: '',
        carrera: '',
        correo_institucional: '',
        estado: 'activo'
    });

const cargarEstudiantes = async () => {

    try {

        setCargando(true);

        const response = await api.get('/estudiantes');

        setEstudiantes(response.data);

    } catch (error) {

        console.error('Error al cargar estudiantes:', error);

    } finally {

        setCargando(false);

    }
};

    useEffect(() => {
        cargarEstudiantes();
    }, []);

    const cambiarCampo = (e) => {

        setFormulario({
            ...formulario,
            [e.target.name]: e.target.value
        });

    };

    const guardar = async (e) => {

        e.preventDefault();

        try {

            if (editando) {

                await api.put(
                    `/estudiantes/${editando.id_estudiante}`,
                    formulario
                );

            } else {

                await api.post(
                    '/estudiantes',
                    formulario
                );

            }

            limpiarFormulario();
            cargarEstudiantes();

        } catch (error) {

            console.error(
                'Error al guardar estudiante:',
                error.response?.data || error
            );

            alert(
                error.response?.data?.message ||
                'No se pudo guardar el estudiante.'
            );

        }

    };

    const editar = (estudiante) => {

        setEditando(estudiante);

        setFormulario({
            codigo_universitario: estudiante.codigo_universitario || '',
            documento_identidad: estudiante.documento_identidad || '',
            nombres: estudiante.nombres || '',
            apellidos: estudiante.apellidos || '',
            carrera: estudiante.carrera || '',
            correo_institucional: estudiante.correo_institucional || '',
            estado: estudiante.estado || 'activo'
        });

        setMostrarFormulario(true);
    };

    const eliminar = async (id) => {

        if (!confirm('¿Desea eliminar este estudiante?')) {
            return;
        }

        try {

            await api.delete(`/estudiantes/${id}`);

            cargarEstudiantes();

        } catch (error) {

            console.error(error);

            alert('No se pudo eliminar el estudiante.');

        }
    };

    const limpiarFormulario = () => {

        setFormulario({
            codigo_universitario: '',
            documento_identidad: '',
            nombres: '',
            apellidos: '',
            carrera: '',
            correo_institucional: '',
            estado: 'activo'
        });

        setEditando(null);
        setMostrarFormulario(false);
    };

    return (
        <div>

            <div className="page-heading">

                <div>
                    <h2>Estudiantes</h2>

                    <p>
                        Registro y gestión de datos de estudiantes
                    </p>
                </div>

                <button
                    className="btn btn-primary"
                    onClick={() => {
                        limpiarFormulario();
                        setMostrarFormulario(true);
                    }}
                >
                    + Registrar estudiante
                </button>

            </div>

            {mostrarFormulario && (

                <div className="form-card">

                    <div className="form-header">

                        <div>
                            <h3>
                                {editando
                                    ? 'Editar estudiante'
                                    : 'Registrar estudiante'}
                            </h3>

                            <p>
                                Complete la información requerida.
                            </p>
                        </div>

                        <button
                            className="close-button"
                            onClick={limpiarFormulario}
                        >
                            ×
                        </button>

                    </div>

                    <form onSubmit={guardar}>

                        <div className="form-grid">

                            <div className="input-group">
                                <label>Código universitario</label>

                                <input
                                    name="codigo_universitario"
                                    value={formulario.codigo_universitario}
                                    onChange={cambiarCampo}
                                    required
                                />
                            </div>

                            <div className="input-group">
                                <label>Documento de identidad</label>

                                <input
                                    name="documento_identidad"
                                    value={formulario.documento_identidad}
                                    onChange={cambiarCampo}
                                    required
                                />
                            </div>

                            <div className="input-group">
                                <label>Nombres</label>

                                <input
                                    name="nombres"
                                    value={formulario.nombres}
                                    onChange={cambiarCampo}
                                    required
                                />
                            </div>

                            <div className="input-group">
                                <label>Apellidos</label>

                                <input
                                    name="apellidos"
                                    value={formulario.apellidos}
                                    onChange={cambiarCampo}
                                    required
                                />
                            </div>

                            <div className="input-group">
                                <label>Carrera</label>

                                <input
                                    name="carrera"
                                    value={formulario.carrera}
                                    onChange={cambiarCampo}
                                    required
                                />
                            </div>

                            <div className="input-group">
                                <label>Correo institucional</label>

                                <input
                                    type="email"
                                    name="correo_institucional"
                                    value={formulario.correo_institucional}
                                    onChange={cambiarCampo}
                                    required
                                />
                            </div>

                            <div className="input-group">
                                <label>Estado</label>

                                <select
                                    name="estado"
                                    value={formulario.estado}
                                    onChange={cambiarCampo}
                                >
                                    <option value="activo">
                                        Activo
                                    </option>

                                    <option value="inactivo">
                                        Inactivo
                                    </option>
                                </select>
                            </div>

                        </div>

                        <div className="form-actions">

                            <button
                                type="button"
                                className="btn btn-secondary"
                                onClick={limpiarFormulario}
                            >
                                Cancelar
                            </button>

                            <button
                                type="submit"
                                className="btn btn-primary"
                            >
                                {editando
                                    ? 'Guardar cambios'
                                    : 'Registrar estudiante'}
                            </button>

                        </div>

                    </form>

                </div>

            )}

            <div className="table-card">

                <div className="table-header">
                    <h3>Estudiantes registrados</h3>

                    <span>
                        {estudiantes.length} registros
                    </span>
                </div>

                {cargando ? (

                    <div className="empty-state">
                        Cargando estudiantes...
                    </div>

                ) : estudiantes.length === 0 ? (

                    <div className="empty-state">
                        No hay estudiantes registrados.
                    </div>

                ) : (

                    <div className="table-wrapper">

                        <table>

                            <thead>

                                <tr>
                                    <th>Código</th>
                                    <th>Estudiante</th>
                                    <th>CI</th>
                                    <th>Carrera</th>
                                    <th>Correo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>

                            </thead>

                            <tbody>

                                {estudiantes.map((estudiante) => (

                                    <tr key={estudiante.id_estudiante}>

                                        <td>
                                            {estudiante.codigo_universitario}
                                        </td>

                                        <td>
                                            <strong>
                                                {estudiante.nombres}{' '}
                                                {estudiante.apellidos}
                                            </strong>
                                        </td>

                                        <td>
                                            {estudiante.documento_identidad}
                                        </td>

                                        <td>
                                            {estudiante.carrera}
                                        </td>

                                        <td>
                                            {estudiante.correo_institucional}
                                        </td>

                                        <td>

                                            <span
                                                className={`status ${
                                                    estudiante.estado === 'activo'
                                                        ? 'status-active'
                                                        : 'status-inactive'
                                                }`}
                                            >
                                                {estudiante.estado}
                                            </span>

                                        </td>

                                        <td>

                                            <div className="action-buttons">

                                                <button
                                                    className="btn-small btn-edit"
                                                    onClick={() => editar(estudiante)}
                                                >
                                                    Editar
                                                </button>

                                                <button
                                                    className="btn-small btn-delete"
                                                    onClick={() => eliminar(estudiante.id_estudiante)}
                                                >
                                                    Eliminar
                                                </button>

                                            </div>

                                        </td>

                                    </tr>

                                ))}

                            </tbody>

                        </table>

                    </div>

                )}

            </div>

        </div>
    );
}

export default Estudiantes;