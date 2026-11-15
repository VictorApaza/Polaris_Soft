# Escenarios de prueba por rol (QA)

Datos de partida sugeridos: 1 administrador, 1 docente con ficha y cuenta vinculadas, 1 usuario de control, una materia con un grupo a cargo del docente, un examen próximo con ambiente, 3 estudiantes asignados al grupo (uno sin foto).

## A. Acceso y roles
| # | Escenario | Pasos | Resultado esperado |
|---|---|---|---|
| A1 | Cada rol entra a su panel | Iniciar sesión con cada cuenta | Administrador → "Panel del administrador"; Docente → "Panel del docente"; Control → "Panel del personal de control" |
| A2 | Menú por rol | Revisar el menú lateral de cada rol | Admin: Usuarios, Materias, Exámenes, Ambientes, Control en vivo, Reportes, Transparencia (sin "Verificar ingreso"). Docente: Mis materias, Habilitar estudiantes, Verificar ingreso, Control en vivo, Reportes, Transparencia. Control: Verificar ingreso, Control en vivo, Transparencia |
| A3 | Solo el administrador cambia roles | Como docente o control, abrir `/usuarios` | 403 "No tienes permiso" |
| A4 | El administrador no verifica ingresos | Como administrador, abrir `/verificacion` | 403 |
| A5 | Menú no se corta | Como administrador abrir Usuarios › Docentes (menú Usuarios desplegado) | Todos los enlaces, incluido "Transparencia", se alcanzan con scroll en el menú |
| A6 | Contraseña de estudiante | Portal del estudiante: cambiar contraseña y volver a entrar | Entra con la nueva; la cédula ya no sirve; el administrador puede restablecerla (candado) |

## B. Administrador
| # | Escenario | Resultado esperado |
|---|---|---|
| B1 | Importar docentes con un archivo correcto | Mensaje "Se importaron N docentes" |
| B2 | Importar docentes con una fila errónea (CI repetido, grado inválido) | No se importa ninguno; lista fila y columna del error |
| B3 | Docente con CI y complemento "1A" | Se guarda; el complemento acepta máximo 2 caracteres |
| B4 | Materias › "N grupos" | Se despliegan los grupos; se puede agregar, editar y eliminar un grupo y asignar docente |
| B5 | Crear cuenta de docente con el mismo nombre y apellido de su ficha | El docente entra y ve solo sus materias |
| B6 | Importar estudiantes con plantilla CSV | Se asignan a la materia y grupo elegidos |
| B7 | Cargar fotos por ZIP (`codigo.jpg`) con un archivo mal nombrado | Se cargan las válidas y se lista el no asociado con su motivo |

## C. Docente
| # | Escenario | Resultado esperado |
|---|---|---|
| C1 | Solo ve lo suyo | En Mis materias, Habilitar estudiantes, Reportes y Control en vivo solo hay materias y exámenes de sus grupos |
| C2 | Acciones directas desde Inicio | En "Próximos exámenes": Habilitar, Verificar, En vivo y Reporte abren la pantalla con el examen ya elegido |
| C3 | Habilitar a un estudiante | Aparece en "Habilitados"; el que no está habilitado es rechazado al verificar |
| C4 | Registrar una falta (copia) con foto de prueba | Aparece en Reportes › examen con su argumento y la foto |
| C5 | Intentar abrir un examen de otro docente (URL directa) | 403 |

## D. Personal de control
| # | Escenario | Resultado esperado |
|---|---|---|
| D1 | Verificar por código, CI y QR (cámara) | Con QR la verificación es automática, sin botón; todo cabe en pantalla |
| D2 | Estudiante habilitado con foto | HABILITADO y se puede autorizar el ingreso con hora de servidor |
| D3 | Estudiante sin foto válida | "SIN FOTO VÁLIDA": no se puede autorizar |
| D4 | Estudiante no habilitado / inactivo | Rechazado con el motivo y queda el intento registrado |
| D5 | Segundo ingreso del mismo estudiante | Alerta amarilla "ya ingresó" con fecha, hora y ambiente anteriores |
| D6 | Examen o ambiente equivocado | "EXAMEN INCORRECTO" / "AMBIENTE INCORRECTO" con lo que corresponde |
| D7 | Suplantación | Se registran nombre, CI y foto del suplantador |
| D8 | Expulsar a un estudiante que ya ingresó | Marca EXPULSADO con motivo y hora; no puede reingresar |
| D9 | Situación excepcional (cambio de ambiente) | Queda registrada y se puede marcar como REVISADA |
| D10 | Control en vivo | Un ingreso nuevo aparece en ≤ 5 s y sale de Pendientes; si se corta la conexión sale el aviso con "Reintentar" |

## E. Estudiante
| # | Escenario | Resultado esperado |
|---|---|---|
| E1 | Subir foto borrosa, oscura o con lentes oscuros | "La foto no es válida" y no se guarda |
| E2 | Subir foto buena | Se acepta |
| E3 | Cambiar contraseña con la actual incorrecta | Mensaje de error |

## F. Interfaz
| # | Escenario | Resultado esperado |
|---|---|---|
| F1 | Misma estructura en todas las pantallas | Ruta de navegación arriba, título, botones de acción a la derecha y mensajes de éxito/error debajo del título |
| F2 | Celular (ancho angosto) | Las tablas hacen scroll horizontal y los botones no se salen de la pantalla |
