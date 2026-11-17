import { api } from '../utils/api.js?v=20261016-teacher-attendance-v2';

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const resources = {
    estudiantes: {
        title: 'Estudiantes',
        description: 'Crear, editar y eliminar estudiantes registrados.',
        fields: [
            { name: 'nombre', label: 'Nombre', required: true },
            { name: 'apellido', label: 'Apellido', required: true },
            { name: 'direccion', label: 'Dirección', required: true },
            { name: 'telefono', label: 'Teléfono', required: true },
        ],
    },
    docentes: {
        title: 'Docentes',
        description: 'Crear, editar y eliminar docentes registrados.',
        fields: [
            { name: 'nombre', label: 'Nombre', required: true },
            { name: 'apellido', label: 'Apellido', required: true },
            { name: 'telefono', label: 'Teléfono' },
        ],
    },
    materias: {
        title: 'Materias',
        description: 'Administrar el catálogo de materias del sistema.',
        fields: [{ name: 'nombre_materia', label: 'Nombre de la materia', required: true }],
    },
    cursos: {
        title: 'Cursos',
        description: 'Administrar cursos, nivel y paralelo.',
        fields: [
            { name: 'nombre_curso', label: 'Nombre del curso', required: true },
            { name: 'nivel', label: 'Nivel', required: true },
            { name: 'paralelo', label: 'Paralelo', required: true },
        ],
    },
    asignaciones: {
        title: 'Asignaciones',
        description: 'Vincular docentes con materias y cursos.',
        fields: [
            { name: 'cod_docente', label: 'Docente', options: 'docentes', display: (item) => `${item.nombre} ${item.apellido}` },
            { name: 'cod_materia', label: 'Materia', options: 'materias', display: (item) => item.nombre_materia },
            { name: 'cod_curso', label: 'Curso', options: 'cursos', display: (item) => `${item.nombre_curso} · ${item.nivel} ${item.paralelo}` },
        ],
    },
    inscripciones: {
        title: 'Inscripciones',
        description: 'Asignar estudiantes a cursos y gestiones.',
        fields: [
            { name: 'cod_estudiante', label: 'Estudiante', options: 'estudiantes', display: (item) => `${item.nombre} ${item.apellido}` },
            { name: 'cod_curso', label: 'Curso', options: 'cursos', display: (item) => `${item.nombre_curso} · ${item.nivel} ${item.paralelo}` },
            { name: 'gestion', label: 'Gestión', type: 'number', required: true },
        ],
    },
    asistencias: {
        title: 'Asistencias',
        description: 'Consultar, registrar y corregir asistencias.',
        dependencies: ['materias', 'cursos'],
        fields: [
            { name: 'cod_estudiante', label: 'Estudiante', options: 'estudiantes', display: (item) => `${item.nombre} ${item.apellido}` },
            { name: 'cod_asignacion', label: 'Asignación', options: 'asignaciones', display: (item, data) => {
                const subject = data.materias.find((value) => Number(value.id) === Number(item.cod_materia));
                const course = data.cursos.find((value) => Number(value.id) === Number(item.cod_curso));
                return `${subject?.nombre_materia || item.cod_materia} · ${course?.nombre_curso || item.cod_curso}`;
            } },
            { name: 'fecha', label: 'Fecha', type: 'date', required: true },
            { name: 'estado', label: 'Estado', values: ['Presente', 'Ausente', 'Licencia', 'Retraso'] },
            { name: 'observacion', label: 'Observación', required: true },
        ],
    },
};

const optionEndpoints = {
    docentes: 'docentes',
    materias: 'materias',
    cursos: 'cursos',
    estudiantes: 'estudiantes',
    asignaciones: 'asignaciones',
};

const showMessage = (message, isError = false) => {
    const element = document.getElementById('ResourceMessage');
    element.textContent = message;
    element.className = `mt-4 rounded-md px-4 py-3 text-sm font-semibold ${isError ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'}`;
};

export const initAdminResource = async (resourceName, session) => {
    const config = resources[resourceName];
    if (!config) throw new Error('El módulo solicitado no existe.');

    const title = document.getElementById('ResourceTitle');
    const description = document.getElementById('ResourceDescription');
    const form = document.getElementById('ResourceForm');
    const head = document.getElementById('ResourceHead');
    const rows = document.getElementById('ResourceRows');
    const data = {};

    title.textContent = config.title;
    description.textContent = config.description;

    const requiredOptions = [
        ...config.fields.filter((field) => field.options).map((field) => field.options),
        ...(config.dependencies || []),
    ];
    for (const endpoint of new Set(requiredOptions)) {
        const values = await api.get(optionEndpoints[endpoint]);
        if (!Array.isArray(values)) throw new Error(`El API no devolvió una lista válida para ${endpoint}.`);
        data[endpoint] = values;
    }

    const renderControl = (field) => {
        if (field.options) {
            const options = (data[field.options] || []).map((item) => {
                const label = field.display ? field.display(item, data) : item.id;
                return `<option value="${escapeHtml(item.id)}">${escapeHtml(label)}</option>`;
            }).join('');
            return `<select id="ResourceField-${field.name}" name="${field.name}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-normal"><option value="">Selecciona una opción</option>${options}</select>`;
        }
        if (field.values) {
            return `<select id="ResourceField-${field.name}" name="${field.name}" required class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-normal">${field.values.map((value) => `<option>${escapeHtml(value)}</option>`).join('')}</select>`;
        }
        return `<input id="ResourceField-${field.name}" name="${field.name}" type="${field.type || 'text'}" ${field.required ? 'required' : ''} class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-normal">`;
    };

    form.innerHTML = `<input id="ResourceId" type="hidden">${config.fields.map((field) => `
        <label class="text-sm font-semibold text-slate-700">${escapeHtml(field.label)}
            ${renderControl(field)}
        </label>
    `).join('')}
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-md bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">Guardar</button>
            <button id="ResourceCancel" type="button" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Limpiar</button>
        </div>`;

    head.innerHTML = `<tr><th class="px-4 py-3">ID</th>${config.fields.map((field) => `<th class="px-4 py-3">${escapeHtml(field.label)}</th>`).join('')}<th class="px-4 py-3 text-right">Acciones</th></tr>`;

    const displayValue = (field, item) => {
        if (!field.options) return item[field.name];
        const entry = (data[field.options] || []).find((option) => Number(option.id) === Number(item[field.name]));
        return field.display && entry ? field.display(entry, data) : (entry?.id ?? item[field.name]);
    };

    const loadRows = async () => {
        rows.innerHTML = `<tr><td colspan="${config.fields.length + 2}" class="px-4 py-6 text-center text-slate-500">Cargando...</td></tr>`;
        const items = await api.get(resourceName);
        if (!Array.isArray(items)) throw new Error(`El API no devolvió una lista válida de ${config.title.toLowerCase()}.`);
        rows.innerHTML = items.length ? items.map((item) => `
            <tr>
                <td class="px-4 py-3 font-semibold">${escapeHtml(item.id)}</td>
                ${config.fields.map((field) => `<td class="px-4 py-3">${escapeHtml(displayValue(field, item))}</td>`).join('')}
                <td class="whitespace-nowrap px-4 py-3 text-right">
                    <button type="button" data-edit-resource="${escapeHtml(item.id)}" class="mr-2 rounded px-2 py-1 font-semibold text-cyan-700 hover:bg-cyan-50">Editar</button>
                    <button type="button" data-delete-resource="${escapeHtml(item.id)}" class="rounded px-2 py-1 font-semibold text-red-700 hover:bg-red-50">Eliminar</button>
                </td>
            </tr>
        `).join('') : `<tr><td colspan="${config.fields.length + 2}" class="px-4 py-6 text-center text-slate-500">No hay registros.</td></tr>`;
        return items;
    };

    const clearForm = () => {
        form.reset();
        document.getElementById('ResourceId').value = '';
    };
    let items = [];

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const id = document.getElementById('ResourceId').value;
        const payload = Object.fromEntries(new FormData(form).entries());
        for (const field of config.fields) {
            if (field.type === 'number' || field.options) payload[field.name] = Number(payload[field.name]);
        }
        if (resourceName === 'asistencias') {
            const existingItem = items.find((item) => String(item.id) === id);
            payload.cod_usuario_registro = existingItem?.cod_usuario_registro ?? session.id;
        }

        try {
            const result = id ? await api.put(`${resourceName}/${id}`, payload) : await api.post(resourceName, payload);
            if (result?.estado !== true) throw new Error(result?.message || 'No se pudo guardar el registro.');
            clearForm();
            showMessage(result.message);
            items = await loadRows();
        } catch (error) {
            showMessage(error.message, true);
        }
    });

    document.getElementById('ResourceCancel').addEventListener('click', clearForm);
    rows.addEventListener('click', async (event) => {
        if (!(event.target instanceof Element)) return;
        const editButton = event.target.closest('[data-edit-resource]');
        const deleteButton = event.target.closest('[data-delete-resource]');
        try {
            if (editButton) {
                const item = items.find((value) => String(value.id) === editButton.dataset.editResource);
                if (!item) throw new Error('No se encontró el registro seleccionado.');
                document.getElementById('ResourceId').value = item.id;
                for (const field of config.fields) {
                    const input = form.elements.namedItem(field.name);
                    input.value = item[field.name] ?? '';
                }
            } else if (deleteButton && window.confirm('¿Deseas eliminar este registro?')) {
                const result = await api.del(`${resourceName}/${deleteButton.dataset.deleteResource}`);
                if (result?.estado !== true) throw new Error(result?.message || 'No se pudo eliminar el registro.');
                showMessage(result.message);
                items = await loadRows();
            }
        } catch (error) {
            showMessage(error.message, true);
        }
    });

    items = await loadRows();
};
