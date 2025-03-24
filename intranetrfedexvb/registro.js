// registro.js
document.getElementById("registro-form").addEventListener("submit", function(event) {
    event.preventDefault();
    var id = document.getElementById("id").value;
    var nombre = document.getElementById("nombre").value;
    var apellidos = document.getElementById("apellidos").value;
    var rango = document.getElementById("rango").value;
    var ncuenta = document.getElementById("ncuenta").value;
    var email = document.getElementById("email").value;
    var password = document.getElementById("password").value;
    // Enviar solicitud de registro
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "registro.php", true);
    xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhr.send("&id=" + id + "&nombre=" + nombre + "&apellidos=" + apellidos + "&rango=" + rango + "&ncuenta=" + ncuenta + "&email=" + email + "&password=" + password);
  });