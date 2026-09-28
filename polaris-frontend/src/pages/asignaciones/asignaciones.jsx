import { useEffect, useState } from 'react';
import api from '../../api/api';

function Asignaciones() {
    const [asignaciones, setAsignaciones] = useState([]);
    const [estudiantes, setEstudiantes] = useState([]);
    const [materias, setMaterias] = useState([]);
    const [grupos, setGrupos] = useState([]);

    const [form, setForm] = useState({
        estudiante_id: '',
        materia_id: '',
        grupo_id: '',
        docente_id: '',
    });

    const [editando, setEditando] = useState(null);
    const [errores, setErrores] = useState({});
    const [mensaje, setMensaje] = useState('');

    useEffect(() => {
        cargarDatos();
    }, []);

    const cargarDatos = async () => {
    try {
        const [
            asignacionesResponse,
            estudiantesResponse,
            materiasResponse,
        ] = await Promise.all([
            api.get('/asignaciones'),
            api.get('/estudiantes'),
            api.get('/materias'),
        ]);

        const asignacionesData = Array.isArray(asignacionesResponse.data)
            ? asignacionesResponse.data
            : asignacionesResponse.data.data || [];

        const estudiantesData = Array.isArray(estudiantesResponse.data)
            ? estudiantesResponse.data
            : estudiantesResponse.data.data || [];

        const materiasData = Array.isArray(materiasResponse.data)
            ? materiasResponse.data
            : materiasResponse.data.data || [];

        setAsignaciones(asignacionesData);
        setEstudiantes(estudiantesData);
        setMaterias(materiasData);

    } catch (error) {
        console.error('Error al cargar datos:', error);
        setMensaje('Error al cargar los datos.');
    }
};

const cargarGrupos = async (materiaId) => {
    if (!materiaId) {
        setGrupos([]);
        return;
    }

    try {
        const response = await api.get('/grupos', {
            params: {
                materia_id: materiaId,
            },
        });

        const gruposData = Array.isArray(response.data)
            ? response.data
            : response.data.data || [];

        setGrupos(gruposData);

    } catch (error) {
        console.error('Error al cargar grupos:', error);
        setGrupos([]);
    }
};

    const handleChange = async (e) => {
        const { name, value } = e.target;

        setForm((prev) => ({
            ...prev,
            [name]: value,
        }));

        setErrores({});

        if (name === 'materia_id') {
            setForm((prev) => ({
                ...prev,
                materia_id: value,
                grupo_id: '',
                docente_id: '',
            }));

            await cargarGrupos(value);
        }

        if (name === 'grupo_id') {
            const grupo = grupos.find(
                (item) => String(item.id) === String(value)
            );

            setForm((prev) => ({
                ...prev,
                grupo_id: value,
                docente_id: grupo ? grupo.docente_id : '',
            }));
        }
    };

    const guardarAsignacion = async (e) => {
        e.preventDefault();

        setErrores({});
        setMensaje('');

        try {
            if (editando) {
                await api.put(`/asignaciones/${editando}`, form);
                setMensaje('Asignación actualizada correctamente.');
            } else {
                await api.post('/asignaciones', form);
                setMensaje('Asignación creada correctamente.');
            }

            limpiarFormulario();
            cargarDatos();
        } catch (error) {
            console.error(error);

            if (error.response?.status === 422) {
                setErrores(error.response.data.errors || {});
            } else {
                setMensaje(
                    error.response?.data?.message ||
                    'Ocurrió un error al guardar la asignación.'
                );
            }
        }
    };

    const editarAsignacion = async (asignacion) => {
        setEditando(asignacion.id);

        const materiaId = asignacion.materia_id;

        await cargarGrupos(materiaId);

        setForm({
            estudiante_id: asignacion.estudiante_id,
            materia_id: materiaId,
            grupo_id: asignacion.grupo_id,
            docente_id: asignacion.docente_id,
        });

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };

    const eliminarAsignacion = async (id) => {
        if (!window.confirm('¿Deseas eliminar esta asignación?')) {
            return;
        }

        try {
            await api.delete(`/asignaciones/${id}`);

            setMensaje('Asignación eliminada correctamente.');

            if (editando === id) {
                limpiarFormulario();
            }

            cargarDatos();
        } catch (error) {
            console.error(error);

            setMensaje(
                error.response?.data?.message ||
                'No se pudo eliminar la asignación.'
            );
        }
    };

    const limpiarFormulario = () => {
        setForm({
            estudiante_id: '',
            materia_id: '',
            grupo_id: '',
            docente_id: '',
        });

        setGrupos([]);
        setEditando(null);
        setErrores({});
    };

    const obtenerError = (campo) => {
        if (!errores[campo]) {
            return null;
        }

        return (
            <small style={{ color: '#dc2626' }}>
                {errores[campo][0]}
            </small>
        );
    };

    return (
        <div className="page-container">
            <div className="page-header">
                <div>
                    <h1>Asignaciones</h1>
                    <p>
                        Asocia estudiantes con materias, grupos y docentes.
                    </p>
                </div>
            </div>

            {/* FORMULARIO */}

            <div className="card">
                <h2>
                    {editando
                        ? 'Editar asignación'
                        : 'Nueva asignación'}
                </h2>

                <form onSubmit={guardarAsignacion}>
                    <div className="form-grid">

                        {/* ESTUDIANTE */}

                        <div className="form-group">
                            <label>Estudiante *</label>

                            <select
                                name="estudiante_id"
                                value={form.estudiante_id}
                                onChange={handleChange}
                            >
                                <option value="">
                                    Seleccionar estudiante
                                </option>

                                {estudiantes.map((estudiante) => (
                                    <option
                                        key={estudiante.id_estudiante}
                                        value={estudiante.id_estudiante}
                                    >
                                        {estudiante.codigo_universitario} -{' '}
                                        {estudiante.nombres}{' '}
                                        {estudiante.apellidos}
                                    </option>
                                ))}
                            </select>

                            {obtenerError('estudiante_id')}
                        </div>

                        {/* MATERIA */}

                        <div className="form-group">
                            <label>Materia *</label>

                            <select
                                name="materia_id"
                                value={form.materia_id}
                                onChange={handleChange}
                            >
                                <option value="">
                                    Seleccionar materia
                                </option>

                                {materias.map((materia) => (
                                    <option
                                        key={materia.id}
                                        value={materia.id}
                                    >
                                        {materia.sigla} - {materia.nombre}
                                    </option>
                                ))}
                            </select>

                            {obtenerError('materia_id')}
                        </div>

                        {/* GRUPO */}

                        <div className="form-group">
                            <label>Grupo *</label>

                            <select
                                name="grupo_id"
                                value={form.grupo_id}
                                onChange={handleChange}
                                disabled={!form.materia_id}
                            >
                                <option value="">
                                    {form.materia_id
                                        ? 'Seleccionar grupo'
                                        : 'Primero selecciona una materia'}
                                </option>

                                {grupos.map((grupo) => (
                                    <option
                                        key={grupo.id}
                                        value={grupo.id}
                                    >
                                        {grupo.nombre}
                                    </option>
                                ))}
                            </select>

                            {obtenerError('grupo_id')}
                        </div>

                        {/* DOCENTE */}

                        <div className="form-group">
                            <label>Docente *</label>

                            <select
                                name="docente_id"
                                value={form.docente_id}
                                disabled
                                onChange={handleChange}
                            >
                                <option value="">
                                    Seleccionar grupo primero
                                </option>

                                {grupos
                                    .filter(
                                        (grupo) =>
                                            String(grupo.id) ===
                                            String(form.grupo_id)
                                    )
                                    .map((grupo) => (
                                        <option
                                            key={grupo.docente_id}
                                            value={grupo.docente_id}
                                        >
                                            {grupo.docente?.nombre ||
                                                'Docente asignado'}
                                        </option>
                                    ))}
                            </select>

                            {obtenerError('docente_id')}
                        </div>

                    </div>

                    <div className="form-actions">
                        <button type="submit">
                            {editando
                                ? 'Actualizar asignación'
                                : 'Guardar asignación'}
                        </button>

                        {editando && (
                            <button
                                type="button"
                                onClick={limpiarFormulario}
                            >
                                Cancelar
                            </button>
                        )}
                    </div>

                    {mensaje && (
                        <div className="message">
                            {mensaje}
                        </div>
                    )}
                </form>
            </div>

            {/* LISTADO */}

            <div className="card">
                <div className="table-header">
                    <h2>Asignaciones registradas</h2>
                </div>

                <div className="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <th>Materia</th>
                                <th>Grupo</th>
                                <th>Docente</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            {asignaciones.length === 0 ? (
                                <tr>
                                    <td colSpan="5">
                                        No existen asignaciones registradas.
                                    </td>
                                </tr>
                            ) : (
                                asignaciones.map((asignacion) => (
                                    <tr key={asignacion.id}>

                                        <td>
                                            {asignacion.estudiante
                                                ? `${asignacion.estudiante.nombres} ${asignacion.estudiante.apellidos}`
                                                : '—'}
                                        </td>

                                        <td>
                                            {asignacion.materia
                                                ? `${asignacion.materia.sigla} - ${asignacion.materia.nombre}`
                                                : '—'}
                                        </td>

                                        <td>
                                            {asignacion.grupo?.nombre || '—'}
                                        </td>

                                        <td>
                                            {asignacion.docente?.nombre || '—'}
                                        </td>

                                        <td>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    editarAsignacion(
                                                        asignacion
                                                    )
                                                }
                                            >
                                                Editar
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() =>
                                                    eliminarAsignacion(
                                                        asignacion.id
                                                    )
                                                }
                                            >
                                                Eliminar
                                            </button>
                                        </td>

                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}

export default Asignaciones;