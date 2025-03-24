// intranet.js
document.getElementById("disponibilidad-form").addEventListener("submit", function(event) {
  event.preventDefault();
  var semana = document.getElementById("semana").value;
  var zona = document.getElementById("zona").value;
  var disponible = document.getElementById("disponible").value; // Cambia aquí
  // Enviar solicitud de guardar disponibilidad
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "procesos/guardar_disponibilidad.php", true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xhr.send("semana=" + semana + "&zona=" + zona + "&disponible=" + disponible);
  
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4 && xhr.status === 200) {
      console.log("Data saved successfully!");
    } else {
      console.error("Error saving data:", xhr.statusText);
    }
  };
});

document.getElementById("partidos-form").addEventListener("submit", function(event) {
  event.preventDefault();
  var fecha = document.getElementById("fecha").value;
  var categoria = document.getElementById("categoria").value;
  var equipo_local = document.getElementById("equipo_local").value;
  var equipo_visitante = document.getElementById("equipo_visitante").value;
  var resultado_local = document.getElementById("resultado_local").value;
  var resultado_visitante = document.getElementById("resultado_visitante").value;
  // Enviar solicitud de guardar partido
  var xhr = new XMLHttpRequest();
  xhr.open("POST", "procesos/guardar_partido.php", true);
  xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  xhr.send("fecha=" + fecha + "&categoria=" + categoria + "&equipo_local=" + equipo_local + "&equipo_visitante=" + equipo_visitante + "&resultado_local=" + resultado_local + "&resultado_visitante=" + resultado_visitante);
  
  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4 && xhr.status === 200) {
      console.log("Data saved successfully!");
    } else {
      console.error("Error saving data:", xhr.statusText);
    }
  };
});