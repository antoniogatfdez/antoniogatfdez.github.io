document.addEventListener("DOMContentLoaded", () => {
  const taskList = document.querySelector(".tasks");
  const addTaskButton = document.getElementById("add-task-button");
  const deleteTaskButton = document.getElementById("delete-task-button");
  const newTaskInput = document.getElementById("new-task-input");

  const loadTasks = () => {
    const tasks = JSON.parse(localStorage.getItem("tasks")) || [];
    tasks.forEach((task) => {
      addTaskToDOM(task.text, task.completed);
    });
  };

  const saveTasks = () => {
    const tasks = [];
    document.querySelectorAll(".task-item").forEach((taskItem) => {
      const text = taskItem.querySelector(".task-label").textContent;
      const completed = taskItem.querySelector(".task-checkbox").checked;
      tasks.push({ text, completed });
    });
    localStorage.setItem("tasks", JSON.stringify(tasks));
  };

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
    deleteButton.innerHTML = "🗑️";
    deleteButton.addEventListener("click", () => {
      taskList.removeChild(taskItem);
      saveTasks();
    });

    taskCheckbox.addEventListener("change", () => {
      saveTasks();
    });

    taskItem.appendChild(taskCheckbox);
    taskItem.appendChild(taskLabel);
    taskItem.appendChild(deleteButton);
    taskList.appendChild(taskItem);
  };

  addTaskButton.addEventListener("click", () => {
    const taskText = newTaskInput.value.trim();
    if (taskText === "") return;

    addTaskToDOM(taskText);
    saveTasks();
    newTaskInput.value = "";
  });

  // Detectar la tecla "Enter" en el input
  newTaskInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      const taskText = newTaskInput.value.trim();
      if (taskText === "") return;

      addTaskToDOM(taskText);
      saveTasks();
      newTaskInput.value = "";
    }
  });

  deleteTaskButton.addEventListener("click", () => {
    if (confirm("¿Estás seguro de borrar todos los seleccionados?")) {
      const checkedTasks = document.querySelectorAll(".task-checkbox:checked");

      checkedTasks.forEach((checkbox) => {
        const taskItem = checkbox.closest(".task-item");
        if (taskItem) {
          taskList.removeChild(taskItem);
        }
      });
      saveTasks();
    }
  });

  loadTasks();
});