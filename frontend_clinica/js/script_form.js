var amazonas = ["Seleccione su ciudad","Bagua","Bongará","Chachapoyas","Condorcanqui","Luya","Rodríguez de Mendoza","Utcubamba"];
var ancash = ["Seleccione su ciudad","Aija","Bolognesi","Carhuaz","Casma","Huaraz","Huari","Huarmey","Huaylas","Santa","Yungay"];
var apurimac = ["Seleccione su ciudad","Abancay","Andahuaylas","Antabamba","Aymaraes","Cotabambas","Chincheros","Grau"];
var arequipa = ["Seleccione su ciudad","Arequipa","Camaná","Caravelí","Castilla","Caylloma","Condesuyos","Islay","La Unión"];
var ayacucho = ["Seleccione su ciudad","Cangallo","Huamanga","Huanta","La Mar","Lucanas","Parinacochas"];
var cajamarca = ["Seleccione su ciudad","Cajabamba","Cajamarca","Celendín","Chota","Cutervo","Jaén","San Ignacio"];
var cusco = ["Seleccione su ciudad","Anta","Calca","Canchis","Cusco","Espinar","La Convención","Quispicanchi","Urubamba"];
var huancavelica = ["Seleccione su ciudad","Acobamba","Angaraes","Castrovirreyna","Huancavelica","Tayacaja"];
var huanuco = ["Seleccione su ciudad","Ambo","Dos de Mayo","Huánuco","Leoncio Prado","Pachitea"];
var ica = ["Seleccione su ciudad","Chincha","Ica","Nazca","Palpa","Pisco"];
var junin = ["Seleccione su ciudad","Chanchamayo","Chupaca","Concepción","Huancayo","Jauja","Junín","Satipo","Tarma"];
var la_libertad = ["Seleccione su ciudad","Ascope","Bolívar","Chepén","Gran Chimú","Julcán","Otuzco","Pacasmayo","Pataz","Sánchez Carrión","Santiago de Chuco","Trujillo","Virú"];
var lambayeque = ["Seleccione su ciudad","Chiclayo","Ferreñafe","Lambayeque"];
var lima = ["Seleccione su ciudad","Lima","Barranca","Cajatambo","Canta","Cañete","Huaral","Huarochirí","Huaura","Oyon","Yauyos"];
var loreto = ["Seleccione su ciudad","Alto Amazonas","Loreto","Maynas","Requena","Ucayali"];
var madre_dios = ["Seleccione su ciudad","Manu","Tahuamanu","Tambopata"];
var moquegua = ["Seleccione su ciudad","General Sánchez Cerro","Ilo","Mariscal Nieto"];
var pasco = ["Seleccione su ciudad","Daniel Alcides Carrión","Oxapampa","Pasco"];
var piura = ["Seleccione su ciudad","Ayabaca","Huancabamba","Morropón","Paita","Piura","Sechura","Sullana","Talara"];
var puno = ["Seleccione su ciudad","Azángaro","Carabaya","Chucuito","El Collao","Melgar","Puno","San Román"];
var san_martin = ["Seleccione su ciudad","Bellavista","Lamas","Mariscal Cáceres","Moyobamba","Rioja","San Martín","Tocache"];
var tacna = ["Seleccione su ciudad","Candarave","Jorge Basadre","Tacna","Tarata"];
var tumbes = ["Seleccione su ciudad","Contralmirante Villar","Tumbes","Zarumilla"];
var ucayali = ["Seleccione su ciudad","Atalaya","Coronel Portillo","Padre Abad","Purus"];
var callao = ["Seleccione su ciudad","Callao"];

var Chiclayo = ["Seleccione su distrito","Chiclayo","José Leonardo Ortiz","La Victoria","Pimentel","Reque","Santa Rosa","Monsefú","Eten","Chongoyape"];
var Lambayeque = ["Seleccione su distrito","Lambayeque","Mochumí","Mórrope","Pacora","San José","Túcume","Jayanca","Motupe","Olmos"];
var Ferreñafe = ["Seleccione su distrito","Ferreñafe","Pueblo Nuevo","Pítipo","Cañaris","Incahuasi"];
var Pacasmayo = ["Seleccione su distrito","Guadalupe","Pacasmayo","San Pedro de Lloc","Jequetepeque","San José"];
var Trujillo = ["Seleccione su distrito","Trujillo","Víctor Larco","La Esperanza","El Porvenir","Huanchaco","Moche","Laredo"];
var Chepén = ["Seleccione su distrito","Chepén","Pacanga","Pueblo Nuevo"];
var Ascope = ["Seleccione su distrito","Ascope","Chicama","Chocope","Paiján","Casa Grande"];
var Lima = ["Seleccione su distrito","Miraflores","San Isidro","Surco","San Borja","La Molina","Cercado de Lima","Los Olivos","San Miguel"];
var Tambopata = ["Seleccione su distrito","Tambopata","Inambari","Las Piedras","Laberinto"];
var Manu = ["Seleccione su distrito","Manu","Fitzcarrald","Madre de Dios","Huepetuhe"];
var Tahuamanu = ["Seleccione su distrito","Iberia","Iñapari","Tahuamanu"];
var Ica = ["Seleccione su distrito","Ica","La Tinguiña","Los Aquijes","Parcona","Subtanjalla"];
var Chincha = ["Seleccione su distrito","Chincha Alta","Chincha Baja","El Carmen","Grocio Prado","Pueblo Nuevo","Sunampe"];
var Nazca = ["Seleccione su distrito","Nazca","Changuillo","El Ingenio","Marcona","Vista Alegre"];
var Palpa = ["Seleccione su distrito","Palpa","Llipata","Río Grande","Santa Cruz","Tibillo"];
var Pisco = ["Seleccione su distrito","Pisco","Huancano","Humay","Independencia","Paracas","San Andrés","San Clemente"];

function cargar_ciudades() {
    var dep = document.getElementById("departamento").value;
    var provinciaSeleccion = document.getElementById("provincia");
    provinciaSeleccion.innerHTML = "";
    if (dep === "" || !window[dep]) {
        provinciaSeleccion.innerHTML = "<option value=''>Seleccione su provincia</option>";
        return;
    }
    window[dep].forEach(function (ciudad) {
        var option = document.createElement("option");
        option.value = ciudad === "Seleccione su ciudad" ? "" : ciudad;
        option.text = ciudad;
        provinciaSeleccion.appendChild(option);
    });
}

function cargar_distritos() {
    var provincia = document.getElementById("provincia").value;
    var distritoSeleccion = document.getElementById("distrito");
    distritoSeleccion.innerHTML = "";
    if (provincia === "") {
        distritoSeleccion.innerHTML = "<option value=''>Seleccione su distrito</option>";
        return;
    }
    var distritos = window[provincia] || ["Seleccione su distrito", provincia + " Centro", "Zona Norte", "Zona Sur"];
    distritos.forEach(function (distrito) {
        var option = document.createElement("option");
        option.value = distrito === "Seleccione su distrito" ? "" : distrito;
        option.text = distrito;
        distritoSeleccion.appendChild(option);
    });
}

function toogleSeguro() {
    let seguro = document.querySelector('input[name="seguro"]:checked');
    let aseguradora = document.getElementById("aseguradora");
    let poliza = document.getElementById("poliza");

    if (seguro && seguro.value === "si") {
        aseguradora.disabled = false;
        poliza.disabled = false;
    } else {
        aseguradora.disabled = true;
        poliza.disabled = true;
        aseguradora.value = "";
        poliza.value = "";
    }
}

function agregarAlergia(val) {
    let item = document.createElement("div");
    item.classList.add("item_alergia");
    let newAlergia = document.createElement("input");
    newAlergia.type = "text";
    newAlergia.name = "alergia";
    newAlergia.value = typeof val === 'string' ? val : '';
    newAlergia.placeholder = "Ingrese otra alergia";
    let btnEliminar = document.createElement("button");
    btnEliminar.type = "button";
    btnEliminar.innerText = "✖";
    btnEliminar.classList.add("btnEliminar");
    btnEliminar.onclick = function () { item.remove(); };
    item.appendChild(newAlergia);
    item.appendChild(btnEliminar);
    let boton = document.getElementById("btnAgregarA");
    let contenedor = document.getElementById("container_Alergias");
    contenedor.insertBefore(item, boton);
}

function agregarEnfermedad(val) {
    let item = document.createElement("div");
    item.classList.add("item_enfermedad");
    let newEnfermedad = document.createElement("input");
    newEnfermedad.type = "text";
    newEnfermedad.name = "enfermedad";
    newEnfermedad.value = typeof val === 'string' ? val : '';
    newEnfermedad.placeholder = "Ingrese otra enfermedad";
    let btnEliminar = document.createElement("button");
    btnEliminar.type = "button";
    btnEliminar.innerText = "✖";
    btnEliminar.classList.add("btnEliminar");
    btnEliminar.onclick = function () { item.remove(); };
    item.appendChild(newEnfermedad);
    item.appendChild(btnEliminar);
    let boton = document.getElementById("btnAgregarE");
    let contenedor = document.getElementById("container_Enfermedad");
    contenedor.insertBefore(item, boton);
}

function agregarCirugia(val) {
    let item = document.createElement("div");
    item.classList.add("item_cirugia");
    let newCirugia = document.createElement("input");
    newCirugia.type = "text";
    newCirugia.name = "cirugias";
    newCirugia.value = typeof val === 'string' ? val : '';
    newCirugia.placeholder = "Ingrese otra cirugia";
    let btnEliminar = document.createElement("button");
    btnEliminar.type = "button";
    btnEliminar.innerText = "✖";
    btnEliminar.classList.add("btnEliminar");
    btnEliminar.onclick = function () { item.remove(); };
    item.appendChild(newCirugia);
    item.appendChild(btnEliminar);
    let boton = document.getElementById("btnAgregarC");
    let contenedor = document.getElementById("container_Cirugias");
    contenedor.insertBefore(item, boton);
}

function validarFormulario() {
    let nombre = document.getElementById("nombre").value.trim();
    let apellidos = document.getElementById("apellidos").value.trim();
    let dni = document.getElementById("dni").value.trim();
    let fechaN = document.getElementById("fecha-naci").value.trim();
    let genero = document.getElementById("genero").value;
    let pais = document.getElementById("pais").value;
    let telefono = document.getElementById("telefono").value.trim();
    let telefono_alt = document.getElementById("telefono-alt").value.trim();
    let domicilio = document.getElementById("direccion").value.trim();
    let departamento = document.getElementById("departamento").value;
    let provincia = document.getElementById("provincia").value;
    let distrito = document.getElementById("distrito").value;
    let nombre_emer = document.getElementById("nombre-emer").value.trim();
    let apellido_emer = document.getElementById("apellido-emer").value.trim();
    let parentesco = document.getElementById("parentesco").value;
    let telefono_emer = document.getElementById("telefono-emer").value.trim();
    let telefono_alt_emer = document.getElementById("telefono-alt-emer").value.trim();
    let tipo_sangre = document.getElementById("tipo-sangre").value;
    const edad = parseInt(document.getElementById("edad").value);
    const terminos = document.getElementById("terminos-condiciones").checked;
    const consentimiento = document.getElementById("consentimiento").checked;

    if (nombre === "") { window.ClinicaAPI.notify("Por favor ingrese su nombre.", "error"); return false; }
    if (apellidos === "") { window.ClinicaAPI.notify("Por favor ingrese sus apellidos.", "error"); return false; }
    if (!/^\d{8}$/.test(dni)) { window.ClinicaAPI.notify("Ingrese un DNI válido de 8 dígitos.", "error"); return false; }
    if (isNaN(edad)) { window.ClinicaAPI.notify("Ingrese su edad.", "error"); return false; }
    if (fechaN === "") { window.ClinicaAPI.notify("Ingrese su fecha de nacimiento.", "error"); return false; }
    if (genero === "") { window.ClinicaAPI.notify("Seleccione su género.", "error"); return false; }
    if (pais === "") { window.ClinicaAPI.notify("Seleccione su país de origen.", "error"); return false; }
    if (!/^\d{9}$/.test(telefono)) { window.ClinicaAPI.notify("Ingrese un número de teléfono válido de 9 dígitos.", "error"); return false; }
    if (telefono_alt !== "" && !/^\d{9}$/.test(telefono_alt)) { window.ClinicaAPI.notify("Ingrese un teléfono alternativo válido de 9 dígitos.", "error"); return false; }
    if (telefono_alt !== "" && telefono === telefono_alt) { window.ClinicaAPI.notify("El teléfono alternativo debe ser diferente al principal.", "error"); return false; }
    if (domicilio === "") { window.ClinicaAPI.notify("Debe ingresar su dirección domiciliaria.", "error"); return false; }
    if (!departamento || !provincia || !distrito) { window.ClinicaAPI.notify("Seleccione su Departamento, Provincia y Distrito.", "error"); return false; }
    if (nombre_emer === "" || apellido_emer === "" || parentesco === "") { window.ClinicaAPI.notify("Complete los datos del contacto de emergencia.", "error"); return false; }
    if (!/^\d{9}$/.test(telefono_emer)) { window.ClinicaAPI.notify("Ingrese un teléfono de emergencia válido de 9 dígitos.", "error"); return false; }
    if (tipo_sangre === "") { window.ClinicaAPI.notify("Seleccione su tipo de sangre.", "error"); return false; }
    if (!terminos || !consentimiento) { window.ClinicaAPI.notify("Debe aceptar los Términos y Condiciones y el Consentimiento de Datos Personales (Ley 29733).", "error"); return false; }

    const alergias = Array.from(document.querySelectorAll('#container_Alergias input')).map(i => i.value.trim()).filter(Boolean);
    const enfermedades = Array.from(document.querySelectorAll('#container_Enfermedad input')).map(i => i.value.trim()).filter(Boolean);
    const cirugias = Array.from(document.querySelectorAll('#container_Cirugias input')).map(i => i.value.trim()).filter(Boolean);
    const seguroRadio = document.querySelector('input[name="seguro"]:checked');

    window.ClinicaAPI.request('api/pacientes', {
        method: 'POST',
        body: {
            nombre,
            apellidos,
            dni,
            edad,
            'fecha-naci': fechaN,
            genero,
            pais: pais === 'm' ? 'Peru' : pais,
            telefono,
            'telefono-alt': telefono_alt,
            direccion: domicilio,
            departamento,
            provincia,
            distrito,
            'nombre-emer': nombre_emer,
            'apellido-emer': apellido_emer,
            parentesco,
            'telefono-emer': telefono_emer,
            'telefono-alt-emer': telefono_alt_emer,
            'tipo-sangre': tipo_sangre,
            alergias,
            enfermedades,
            cirugias,
            seguro: seguroRadio ? seguroRadio.value : 'no',
            aseguradora: document.getElementById('aseguradora').value.trim(),
            poliza: document.getElementById('poliza').value.trim(),
            'terminos-condiciones': terminos,
            consentimiento: consentimiento
        }
    }).then(res => {
        if (res.ok) {
            window.ClinicaAPI.notify('Ficha médica guardada correctamente en la base de datos.', 'success');
            setTimeout(() => { window.location.href = 'mis_citas.html'; }, 500);
        } else {
            window.ClinicaAPI.notify(res.data.message || 'Error al guardar la ficha médica.', 'error');
        }
    });

    return false;
}

document.addEventListener('DOMContentLoaded', async () => {
    const res = await window.ClinicaAPI.request('api/perfil');
    if (res.ok && res.data.data && res.data.data.ficha) {
        const f = res.data.data.ficha;
        document.getElementById('nombre').value = f.nombre || '';
        document.getElementById('apellidos').value = f.apellidos || '';
        document.getElementById('dni').value = f.dni || '';
        document.getElementById('edad').value = f.edad || '';
        document.getElementById('fecha-naci').value = f.fecha_nacimiento || '';
        document.getElementById('genero').value = f.genero || '';
        document.getElementById('pais').value = 'm';
        document.getElementById('telefono').value = f.telefono || '';
        document.getElementById('telefono-alt').value = f.telefono_alt || '';
        document.getElementById('direccion').value = f.direccion || '';
        if (f.departamento) {
            document.getElementById('departamento').value = f.departamento;
            cargar_ciudades();
            if (f.provincia) {
                document.getElementById('provincia').value = f.provincia;
                cargar_distritos();
                if (f.distrito) document.getElementById('distrito').value = f.distrito;
            }
        }
        document.getElementById('nombre-emer').value = f.nombre_emergencia || '';
        document.getElementById('apellido-emer').value = f.apellido_emergencia || '';
        document.getElementById('parentesco').value = f.parentesco_emergencia || '';
        document.getElementById('telefono-emer').value = f.telefono_emergencia || '';
        document.getElementById('telefono-alt-emer').value = f.telefono_alt_emergencia || '';
        document.getElementById('tipo-sangre').value = f.tipo_sangre || '';
        document.getElementById('alergias').value = f.alergias || '';
        document.getElementById('enfermedad').value = f.enfermedades || '';
        document.getElementById('cirugias').value = f.cirugias || '';

        if (parseInt(f.tiene_seguro) === 1) {
            const rSi = document.querySelector('input[name="seguro"][value="si"]');
            if (rSi) rSi.checked = true;
            toogleSeguro();
            document.getElementById('aseguradora').value = f.aseguradora || '';
            document.getElementById('poliza').value = f.numero_poliza || '';
        } else {
            const rNo = document.querySelector('input[name="seguro"][value="no"]');
            if (rNo) rNo.checked = true;
        }
        document.getElementById('terminos-condiciones').checked = true;
        document.getElementById('consentimiento').checked = parseInt(f.consentimiento_datos) === 1;

        const arcoSection = document.getElementById('arcoSection');
        if (arcoSection) {
            arcoSection.style.display = 'block';
            document.getElementById('btnRevocarConsentimiento').onclick = async () => {
                const r = await window.ClinicaAPI.request('api/perfil', {
                    method: 'PUT',
                    body: { consentimiento_datos: false }
                });
                if (r.ok) {
                    document.getElementById('consentimiento').checked = false;
                    window.ClinicaAPI.notify('Consentimiento de uso secundario revocado y auditado (Ley 29733).', 'success');
                }
            };
            document.getElementById('btnAnonimizarArco').onclick = async () => {
                if (!confirm('¿Confirma ejercer su derecho ARCO de cancelación y anonimización de datos personales conforme a la Ley N° 29733?')) return;
                const r = await window.ClinicaAPI.request('api/perfil', { method: 'DELETE' });
                if (r.ok) {
                    window.ClinicaAPI.notify(r.data.message, 'success');
                    setTimeout(() => { window.location.href = 'index.html'; }, 700);
                }
            };
        }
    }
});
