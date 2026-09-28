import { useEffect, useState } from 'react';
import api from '../../services/api';

function Examenes() {

    const [examenes, setExamenes] = useState([]);
    const [materias, setMaterias] = useState([]);
    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [editando, setEditando] = useState(null);
    const [cargando, setCargando] = useState(true);

    const [formulario, setFormulario] = useState({
        materia_id: '',
        fecha: '',
        hora: '',
        duracion: '',
        ambiente: '',
        normas_generales: '',
        normas_particulares: ''
    });

    const cargarDatos = async () => {

        try {

            setCargando(true);

            const [examenesResponse, materiasResponse] =
                await Promise.all([
                    api.get('/examenes'),
                    api.get('/materias')
                ]);

            setExamenes(
                examenesResponse.data.data?.data ||
                examenesResponse.data.data ||
                []
            );

            setMaterias(
                materiasResponse.data.data?.data ||
                materiasResponse.data.data ||
                []
            );

        } catch (error) {

            console.error(
                'Error al cargar datos:',
                error.response?.data || error
            );

        } finally {

            setCargando(false);

        }
    };

    useEffect(() => {
        cargarDatos();
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
                    `/examenes/${editando.id_examen}`,
                    formulario
                );

            } else {

                await api.post(
                    '/examenes',
                    formulario
                );

            }

            limpiarFormulario();
            cargarDatos();

        } catch (error) {

            console.error(
                'Error al guardar examen:',
                error.response?.data || error
            );

            alert(
                error.response?.data?.message ||
                'No se pudo guardar el examen.'
            );

        }
    };

    const editar = (examen) => {

        setEditando(examen);

        setFormulario({
            materia_id: examen.materia_id || '',
            fecha: examen.fecha
                ? examen.fecha.substring(0, 10)
                : '',
            hora: examen.hora
                ? examen.hora.substring(0, 5)
                : '',
            duracion: examen.duracion || '',
            ambiente: examen.ambiente || '',
            normas_generales: examen.normas_generales || '',
            normas_particulares: examen.normas_particulares || ''
        });

        setMostrarFormulario(true);
    };

    const eliminar = async (id) => {

        if (!confirm('¿Desea eliminar este examen?')) {
            return;
        }

        try {

            await api.delete(`/examenes/${id}`);

            cargarDatos();

        } catch (error) {

            console.error(error);

            alert('No se pudo eliminar el examen.');

        }
    };

    const limpiarFormulario = () => {

        setFormulario({
            materia_id: '',
            fecha: '',
            hora: '',
            duracion: '',
            ambiente: '',
            normas_generales: '',
            normas_particulares: ''
        });

        setEditando(null);
        setMostrarFormulario(false);
    };

    return (
        <div>

            <div className="page-heading">

                <div>

                    <h2>Exámenes</h2>

                    <p>
                        Registro y gestión de exámenes académicos
                    </p>

                </div>

                <button
                    className="btn btn-primary"
                    onClick={() => {
                        limpiarFormulario();
                        setMostrarFormulario(true);
                    }}
                >
                    + Registrar examen
                </button>

            </div>

            {mostrarFormulario && (

                <div className="form-card">

                    <div className="form-header">

                        <div>

                            <h3>
                                {editando
                                    ? 'Editar examen'
                                    : 'Registrar examen'}
                            </h3>

                            <p>
                                Complete la información del examen.
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

                                <label>
                                    Asignatura
                                </label>

                                <select
                                    name="materia_id"
                                    value={formulario.materia_id}
                                    onChange={cambiarCampo}
                                    required
                                >

                                    <option value="">
                                        Seleccione una asignatura
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

                            </div>

                            <div className="input-group">

                                <label>
                                    Ambiente
                                </label>

                                <input
                                    name="ambiente"
                                    value={formulario.ambiente}
                                    onChange={cambiarCampo}
                                    placeholder="Ej. Aula 204"
                                    required
                                />

                            </div>

                            <div className="input-group">

                                <label>
                                    Fecha
                                </label>

                                <input
                                    type="date"
                                    name="fecha"
                                    value={formulario.fecha}
                                    onChange={cambiarCampo}
                                    required
                                />

                            </div>

                            <div className="input-group">

                                <label>
                                    Hora
                                </label>

                                <input
                                    type="time"
                                    name="hora"
                                    value={formulario.hora}
                                    onChange={cambiarCampo}
                                    required
                                />

                            </div>

                            <div className="input-group">

                                <label>
                                    Duración (minutos)
                                </label>

                                <input
                                    type="number"
                                    name="duracion"
                                    min="1"
                                    value={formulario.duracion}
                                    onChange={cambiarCampo}
                                    required
                                />

                            </div>

                        </div>

                        <div className="input-group">

                            <label>
                                Normas generales
                            </label>

                            <textarea
                                name="normas_generales"
                                value={formulario.normas_generales}
                                onChange={cambiarCampo}
                                placeholder="Normas generales del examen..."
                            />

                        </div>

                        <div className="input-group">

                            <label>
                                Normas particulares
                            </label>

                            <textarea
                                name="normas_particulares"
                                value={formulario.normas_particulares}
                                onChange={cambiarCampo}
                                placeholder="Normas particulares..."
                            />

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
                                    : 'Registrar examen'}
                            </button>

                        </div>

                    </form>

                </div>

            )}

            <div className="table-card">

                <div className="table-header">

                    <h3>
                        Exámenes registrados
                    </h3>

                    <span>
                        {examenes.length} registros
                    </span>

                </div>

                {cargando ? (

                    <div className="empty-state">
                        Cargando exámenes...
                    </div>

                ) : examenes.length === 0 ? (

                    <div className="empty-state">
                        No hay exámenes registrados.
                    </div>

                ) : (

                    <div className="table-wrapper">

                        <table>

                            <thead>

                                <tr>
                                    <th>Asignatura</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Duración</th>
                                    <th>Ambiente</th>
                                    <th>Acciones</th>
                                </tr>

                            </thead>

                            <tbody>

                                {examenes.map((examen) => (

                                    <tr key={examen.id_examen}>

                                        <td>

                                            <strong>
                                                {examen.materia?.nombre ||
                                                    'Sin asignatura'}
                                            </strong>

                                            {examen.materia?.sigla && (
                                                <small className="table-subtitle">
                                                    {examen.materia.sigla}
                                                </small>
                                            )}

                                        </td>

                                        <td>
                                            {examen.fecha
                                                ? examen.fecha.substring(0, 10)
                                                : '-'}
                                        </td>

                                        <td>
                                            {examen.hora
                                                ? examen.hora.substring(0, 5)
                                                : '-'}
                                        </td>

                                        <td>
                                            {examen.duracion} min
                                        </td>

                                        <td>
                                            {examen.ambiente}
                                        </td>

                                        <td>

                                            <div className="action-buttons">

                                                <button
                                                    className="btn-small btn-edit"
                                                    onClick={() => editar(examen)}
                                                >
                                                    Editar
                                                </button>

                                                <button
                                                    className="btn-small btn-delete"
                                                    onClick={() => eliminar(examen.id_examen)}
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

export default Examenes;