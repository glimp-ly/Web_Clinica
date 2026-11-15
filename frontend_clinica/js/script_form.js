
function validarFormulario(){

    let nombre = document.getElementById("nombre").value.trim();
    let apellidos = document.getElementById("apellidos").value.trim();
    let dni = document.getElementById("dni").value.trim();
    let fechaN = document.getElementById("fecha-naci").value.trim();
    let genero = document.getElementById("genero").value;
    let pais = document.getElementById("pais").value;
    let telefono = document.getElementById("telefono").value.trim();
    let telefono_alt = document.getElementById("telefono-alt").value.trim();
    let domicilio = document.getElementById("direccion").value.trim();
    let nombre_emer = document.getElementById("nombre-emer").value.trim();
    let apellido_emer = document.getElementById("apellido-emer").value.trim();
    let parentesco = document.getElementById("parentesco").value;
    let telefono_emer = document.getElementById("telefono-emer").value.trim();
    let telefono_alt_emer = document.getElementById("telefono-alt-emer").value.trim();
    let tipo_sangre = document.getElementById("tipo-sangre").value;

    const edad = parseInt(document.getElementById("edad").value);

    /*DATOS PERSONALES*/
    if(nombre === ""){
        alert("Porfavor ingrese su nombre.");
        return false;
    }
    
    if(apellidos === ""){
        alert("Porfavor ingrese sus apellidos");
        return false;
    }

    if (!/^\d{8}$/.test(dni)) { /*!/^\d{8}$/.test --> valida que el texto tenga exactamente 8 digitos*/
    alert("Ingrese un DNI válido de 8 dígitos");
    return false;
    }

    if(isNaN(edad)){
        alert("Ingrese su edad");
        return false;
    }

    if(fechaN === ""){
        alert("Ingrese su fecha de nacimiento");
        return false;
    }

    if(genero === ""){
        alert("Seleccione su genero");
        return false;
    }

    if(pais === ""){
        alert("Seleccione su pais de origen");
        return false;
    }

    /*DATOS DE CONTACTO*/

    if (!/^\d{9}$/.test(telefono)) { /*!/^\d{9}$/.test --> valida que el texto tenga exactamente 9 digitos*/
    alert("Ingrese un número de telefono válido de 9 dígitos");
    return false;
    }

    if (!/^\d{9}$/.test(telefono_alt)) { /*!/^\d{9}$/.test --> valida que el texto tenga exactamente 9 digitos*/
    alert("Ingrese un número de telefono alternativo válido de 9 dígitos");
    return false;
    }

    if(telefono === telefono_alt){
        alert("El número de telefono alternativo debe ser diferente al número de telefono principal");
        return false;
    }

    if(domicilio === ""){
        alert("Debe ingresar su dirección domiciliaria");
        return false;
    }

    /*CONTACTO DE EMERGENCIA*/

    if(nombre_emer === ""){
        alert("Debe ingrese el nombre/s de su contacto de emergencia");
        return false;
    }

    if(apellido_emer === ""){
        alert("Debe ingresar el apellido/s de su contacto de emergencia");
        return false;
    }

    if(parentesco === ""){
        alert("Debe seleccionar el parentesco de su contacto de emergencia con usted");
        return false;
    }

    if (!/^\d{9}$/.test(telefono_emer)) { /*!/^\d{9}$/.test --> valida que el texto tenga exactamente 9 digitos*/
    alert("Ingrese un número de telefono de su contacto de emergencia válido de 9 dígitos");
    return false;
    }

    if (!/^\d{9}$/.test(telefono_alt_emer)) { /*!/^\d{9}$/.test --> valida que el texto tenga exactamente 9 digitos*/
    alert("Ingrese un número de telefono alternativo de su contacto de emergencia válido de 9 dígitos");
    return false;
    }

    /*INFORMACIÓN MÉDICA*/

    if(tipo_sangre === ""){
        alert("Seleccione su tipo de sangre");
        return false;
    }

    window.location.href = "index.html";

    return false;
}

    var amazonas = new Array("Seleccione su ciudad","Bagua","Bongará","Chachapoyas","Condorcanqui","Luya","Rodríguez de Mendoza","Utcubamba");
    var ancash = new Array("Seleccione su ciudad","Aija","Antonio Raymondi","Asunción","Bolognesi","Carhuaz","Carlos Fermín Fitzcarrald","Casma","Corongo","Huaraz","Huari","Huarmey","Huaylas","Mariscal Luzuriaga","Ocros","Pallasca","Pomabamba","Recuay","Santa","Sihuas","Yungay");
    var apurimac = new Array("Seleccione su ciudad","Abancay","Andahuaylas","Antabamba","Aymaraes","Cotabambas","Chincheros","Grau");
    var arequipa = new Array("Seleccione su ciudad","Arequipa","Camaná","Caravelí","Castilla","Caylloma","Condesuyos","Islay","La Unión");
    var ayacucho = new Array("Seleccione su ciudad","Cangallo","Huamanga","Huanca Sancos","Huanta","La Mar","Lucanas","Parinacochas","Páucar del Sara Sara","Sucre","Víctor Fajardo","Vilcas Huamán");
    var cajamarca = new Array("Seleccione su ciudad","Cajabamba","Cajamarca","Celendín","Chota","Contumazá","Cutervo","Hualgayoc","Jaén","San Ignacio","San Marcos","San Miguel","San Pablo","Santa Cruz");
    var cusco = new Array("Seleccione su ciudad","Acomayo","Anta","Calca","Canas","Canchis","Chumbivilcas","Cusco","Espinar","La Convención","Paruro","Paucartambo","Quispicanchi","Urubamba");
    var huancavelica = new Array("Seleccione su ciudad","Acobamba","Angaraes","Castrovirreyna","Churcampa","Huancavelica","Huaytará","Tayacaja");
    var huanuco = new Array("Seleccione su ciudad","Ambo","Dos de Mayo","Huacaybamba","Huánuco","Lauricocha","Leoncio Prado","Marañón","Pachitea","Puerto Inca","Yarowilca");
    var ica = new Array("Seleccione su ciudad","Chincha","Ica","Nazca","Palpa","Pisco");
    var junin = new Array("Seleccione su ciudad","Chanchamayo","Chupaca","Concepción","Huancayo","Jauja","Junín","Satipo","Tarma","Yauli");
    var la_libertad = new Array("Seleccione su ciudad","Ascope","Bolívar","Chepén","Gran Chimú","Julcán","Otuzco","Pacasmayo","Pataz","Sánchez Carrión","Santiago de Chuco","Trujillo","Virú");
    var lambayeque = new Array("Seleccione su ciudad","Chiclayo","Ferreñafe","Lambayeque");
    var lima = new Array("Seleccione su ciudad","Barranca","Cajatambo","Canta","Cañete","Huaral","Huarochirí","Huaura","Oyon","Yauyos");
    var loreto = new Array("Seleccione su ciudad","Alto Amazonas","Datem del Marañón","Loreto","Mariscal Ramón Castilla","Maynas","Putumayo","Requena","Ucayali");
    var madre_dios = new Array("Seleccione su ciudad","Manu","Tahuamanu","Tambopata");
    var moquegua = new Array("Seleccione su ciudad","General Sánchez Cerro","Ilo","Mariscal Nieto");
    var pasco = new Array("Seleccione su ciudad","Daniel Alcides Carrión","Oxapampa","Pasco");
    var piura = new Array("Seleccione su ciudad","Ayabaca","Huancabamba","Morropón","Paita","Piura","Sechura","Sullana","Talara");
    var puno = new Array("Seleccione su ciudad","Azángaro","Carabaya","Chucuito","El Collao","Huancané","Lampa","Melgar","Moho","Puno","San Antonio de Putina","San Román","Sandia","Yunguyo");
    var san_martin = new Array("Seleccione su ciudad","Bellavista","El Dorado","Huallaga","Lamas","Mariscal Cáceres","Moyobamba","Picota","Rioja","San Martín","Tocache");
    var tacna = new Array("Seleccione su ciudad","Candarave","Jorge Basadre","Tacna","Tarata");
    var tumbes = new Array("Seleccione su ciudad","Contralmirante Villar","Tumbes","Zarumilla");
    var ucayali = new Array("Seleccione su ciudad","Atalaya","Coronel Portillo","Padre Abad","Purus");

    function cargar_ciudades(){
        var dep = document.getElementById("departamento").value;
        var provinciaSeleccion = document.getElementById("provincia");

        provinciaSeleccion.innerHTML = "";

        if(dep === ""){
            provinciaSeleccion.innerHTML = "<option value = ''> Seleccione su ciudad </option>";
            return;
        }

        var ciudades = window[dep];

        ciudades.forEach(function(ciudad){
            var option = document.createElement("option");
            option.value = ciudad;
            option.text = ciudad;
            provinciaSeleccion.appendChild(option);
        });
    }

    /*Lambayeque*/
    var Chiclayo = new Array("Seleccione su distrito","Chiclayo","José Leonardo Ortiz","La Victoria","Pimentel","Reque","Santa Rosa","Monsefú","Eten","Puerto Eten","Chongoyape","Oyotún","Zaña","Nueva Arica","Lagunas");
    var Lambayeque = new Array("Seleccione su distrito","Lambayeque","Mochumí","Mórrope","Pacora","San José","Túcume","Jayanca","Íllimo","Salas","Motupe","Olmos");
    var Ferreñafe = new Array("Seleccione su distrito","Ferreñafe","Pueblo Nuevo","Pítipo","Cañaris","Incahuasi","Manuel Antonio Mesones Muro");

    /*Madre de Dios*/
    var Tambopata = new Array("Seleccione su distrito","Tambopata","Inambari","Las Piedras","Laberinto");
    var Manu = new Array("Seleccione su distrito","Manu","Fitzcarrald","Madre de Dios","Huepetuhe");
    var Tahuamanu = new Array("Seleccione su distrito","Iberia","Iñapari","Tahuamanu");

    /*ICA*/
    var Ica = new Array("Seleccione su distrito","Ica","La Tinguiña","Los Aquijes","Ocucaje","Pachacútec","Parcona","Pueblo Nuevo","Salas","San José de Los Molinos","San Juan Bautista","Santiago","Subtanjalla","Tate","Yauca del Rosario");
    var Chincha = new Array("Seleccione su distrito","Chincha Alta","Chincha Baja","El Carmen","Grocio Prado","Pueblo Nuevo","San Juan Bautista","Sunampe","Tambo de Mora");
    var Nazca = new Array("Seleccione su distrito","Nazca","Changuillo","El Ingenio","Marcona","Vista Alegre");
    var Palpa = new Array("Seleccione su distrito","Palpa","Llipata","Río Grande","Santa Cruz","Tibillo");
    var Pisco = new Array("Seleccione su distrito","Pisco","Huancano","Humay","Independencia","Paracas","San Andrés","San Clemente","Túpac Amaru Inca");


function cargar_distritos(){
    var provincia = document.getElementById("provincia").value;
    var distritoSeleccion = document.getElementById("distrito");

    distritoSeleccion.innerHTML = "";

    if(provincia === ""){
        provinciaSeleccion.innerHTML = "<option value = ''> Seleccione su distrito </option>";
        return;
    }

    var distritos = window[provincia];

    distritos.forEach(function(distrito){
        var option = document.createElement("option");
        option.value = distrito;
        option.text = distrito;
        distritoSeleccion.appendChild(option);
    });
}

function toogleSeguro(){
    let seguro = document.querySelector('input[name="seguro"]:checked');
    let aseguradora = document.getElementById("aseguradora");
    let poliza = document.getElementById("poliza");

    if(seguro && seguro.value === "si"){
        aseguradora.disabled = false;
        poliza.disabled = false;
    }else{
        aseguradora.disabled = true;
        poliza.disabled = true;

        /*Se limpian los campos en caso de seleccionar nuevamente "NO"*/
        aseguradora.value = "";
        poliza.value = "";
    }
}

function agregarAlergia(){

    let item = document.createElement("div");
    item.classList.add("item_alergia");

    let newAlergia = document.createElement("input");
    newAlergia.type = "text";
    newAlergia.name = "alergia";
    newAlergia.placeholder = "Ingrese otra alergia";

    let btnEliminar = document.createElement("button");
    btnEliminar.innerText = "✖";
    btnEliminar.classList.add("btnEliminar");

    btnEliminar.onclick = function() {
        item.remove();
    };

    item.appendChild(newAlergia);
    item.appendChild(btnEliminar);

    let boton = document.getElementById("btnAgregarA");
    let contenedor = document.getElementById("container_Alergias");

    contenedor.insertBefore(item, boton);
}

function agregarEnfermedad(){
    let item = document.createElement("div");
    item.classList.add("item_enfermedad");

    let newEnfermedad = document.createElement("input");
    newEnfermedad.type = "text";
    newEnfermedad.name = "enfermedad";
    newEnfermedad.placeholder = "Ingrese otra enfermedad";

    let btnEliminar = document.createElement("button");
    btnEliminar.innerText = "✖";
    btnEliminar.classList.add("btnEliminar");

    btnEliminar.onclick = function() {
        item.remove();
    };

    item.appendChild(newEnfermedad);
    item.appendChild(btnEliminar);

    let boton = document.getElementById("btnAgregarE");
    let contenedor = document.getElementById("container_Enfermedad");

    contenedor.insertBefore(item, boton);
}

function agregarCirugia(){
    let item = document.createElement("div");
    item.classList.add("item_cirugia");

    let newCirugia = document.createElement("input");
    newCirugia.type = "text";
    newCirugia.name = "cirugias";
    newCirugia.placeholder = "Ingrese otra cirugia";

    let btnEliminar = document.createElement("button");
    btnEliminar.innerText = "✖";
    btnEliminar.classList.add("btnEliminar");

    btnEliminar.onclick = function() {
        item.remove();
    };

    item.appendChild(newCirugia);
    item.appendChild(btnEliminar);

    let boton = document.getElementById("btnAgregarC");
    let contenedor = document.getElementById("container_Cirugias");

    contenedor.insertBefore(item, boton);

}
