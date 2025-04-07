document.addEventListener("DOMContentLoaded", () => {
  const taskList = document.querySelector(".tasks");
  const addTaskButton = document.getElementById("add-task-button");
  const newTaskInput = document.getElementById("new-task-input");

  // Cargar tareas desde LocalStorage
  const loadTasks = () => {
    const tasks = JSON.parse(localStorage.getItem("tasks")) || [];
    tasks.forEach((task) => {
      addTaskToDOM(task.text, task.completed);
    });
  };

  // Guardar tareas en LocalStorage
  const saveTasks = () => {
    const tasks = [];
    document.querySelectorAll(".task-item").forEach((taskItem) => {
      const text = taskItem.querySelector(".task-label").textContent;
      const completed = taskItem.querySelector(".task-checkbox").checked;
      tasks.push({ text, completed });
    });
    localStorage.setItem("tasks", JSON.stringify(tasks));
  };

  // Añadir tarea al DOM
  const addTaskToDOM = (taskText, completed = false) => {
    const taskItem = document.createElement("li");
    taskItem.className = "task-item";

    const taskCheckbox = document.createElement("input");
    taskCheckbox.type = "checkbox";
    taskCheckbox.className = "task-checkbox";
    taskCheckbox.checked = completed;

    const taskLabel = document.createElement("label");
    taskLabel.className = "task-label";
    taskLabel.textContent = taskText;

    const deleteButton = document.createElement("button");
    deleteButton.className = "task-delete";
    deleteButton.innerHTML = "🗑️"; // Icono de papelera
    deleteButton.addEventListener("click", () => {
      taskList.removeChild(taskItem); // Elimina la tarea del DOM
      saveTasks(); // Actualiza LocalStorage
    });

    // Añadir funcionalidad al checkbox
    taskCheckbox.addEventListener("change", () => {
      saveTasks(); // Actualiza LocalStorage al marcar/desmarcar
    });

    // Añadir elementos al DOM
    taskItem.appendChild(taskCheckbox);
    taskItem.appendChild(taskLabel);
    taskItem.appendChild(deleteButton);
    taskList.appendChild(taskItem);
  };

  // Manejar el evento de añadir nueva tarea
  addTaskButton.addEventListener("click", () => {
    const taskText = newTaskInput.value.trim();
    if (taskText === "") return;

    addTaskToDOM(taskText); // Añadir tarea al DOM
    saveTasks(); // Guardar en LocalStorage
    newTaskInput.value = ""; // Limpiar el campo de entrada
  });

  // Cargar tareas al iniciar la página
  loadTasks();
});