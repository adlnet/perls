const maxCharacterLimit = 50;
// store.js This is a basic store.
const store = new Vuex.Store({
  state: {
    tasks: [],
    csrfToken: '',
  },
  mutations: {
    SET_TASKS(state, tasks) {
      state.tasks = tasks;
    },
    SET_TOKEN(state, token) {
      state.csrfToken = token;
    }
  },
  actions: {
    async fetchSessionToken() {
      try {
        const token = await fetch('/session/token');
        const csrfToken = await token.text();
        store.commit('SET_TOKEN', csrfToken);
      } catch { }
    },
    async fetchTasks() {
      try {
        const userId = document.location.pathname.split('/')[2];
        const response = await fetch(`/api/tasks/${userId}`, {
          headers: {
            'Content-Type': 'application/json'
          },
        });
        const tasks = await response.json();
        tasks.forEach(task => {
          task.errors = [];
          task.isEditing = false;
          task.nameOriginal = task.name;
        });
        store.commit('SET_TASKS', tasks);
      } catch {}
    },
    addNewTask: function (store) {
      if (store.state.tasks.length >= 10) {
        return;
      }
      store.state.tasks.unshift({
        id: -Math.floor(Math.random() * 50),
        errors: [],
        isNew: true,
        isEditing: true,
        weight: -store.state.tasks.length,
      });
    },
    async createTask(store, task) {
      if (!task.isNew) {
        return;
      }

      try {
        const userId = document.location.pathname.split('/')[2];
        const url = '/entity/task';
        let payload = {
          type: 'user_task',
          weight: {
            value: task.weight
          },
          name: {
            value: task.name
          }
        };
        if (userId !== drupalSettings.user.uid) {
          payload.user_id = { target_id: userId }
        }
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': store.state.csrfToken,
          },
          body: JSON.stringify(payload)
        });
        const taskObject = await response.json();
        store.state.tasks = store.state.tasks.map(_task => {
          if (_task.id !== task.id) {
            return _task;
          }
          task.id = taskObject.id[0].value;
          task.name = taskObject.name[0].value;
          task.nameOriginal = taskObject.name[0].value;
          task.weight = taskObject.weight[0].value;
          task.isNew = false;
          task.isEditing = false;
          task.errors = [];
          return task;
        });
      } catch {}
    },
    async updateTask(store, task) {
      try {
        const url = `/task/${task.id}`;
        await fetch(url, {
          method: 'PATCH',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-Token': store.state.csrfToken,
          },
          body: JSON.stringify({
            type: 'user_task',
            weight: {
              value: task.weight
            },
            name: {
              value: task.name
            },
          })
        });
        task.nameOriginal = task.name;
        task.isEditing = false;
      } catch { }
    },
    async deleteTask(store, task) {
      try {
        await fetch(`/task/${task.id}`, {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': store.state.csrfToken,
          },
        });
        var tasks = store.state.tasks.filter(_task => _task.id !== task.id);
        store.commit('SET_TASKS', tasks);
      } catch { }
    },
    async markTaskAsComplete(store, task) {
      try {
        await fetch(`/task/${task.id}`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': store.state.csrfToken,
          },
          body: JSON.stringify({
            type: 'user_task',
            completion_date: {
              value: (new Date()).toISOString().slice(0, -5) + "Z"
            }
          })
        });

        var tasks = store.state.tasks.filter(_task => _task.id !== task.id);
        store.commit('SET_TASKS', tasks);
      } catch {}
    },
    cancelEditTask: function (store, task) {
      if (task.isNew) {
        store.state.tasks = store.state.tasks.filter(_task => task.id !== _task.id);
        return
      }
      task.name = task.nameOriginal;
      task.isEditing = !task.isEditing;
      task.errors = [];
    },
  },
});

// app Vue instance
var app = new Vue({
  store,
  data: {
    dialogActive: false,
    userDragEnabled: false,
    isPageReady: false
  },
  // computed properties
  computed: {
    tasks: {
      get() {
        return this.$store.state.tasks;
      },
      set(value) {
        // Very naive approach.
        // We could calculate the best weight for the items
        // that moved position; this just bases weight
        // by index which could trigger 10 [tiny] API calls.
        const newTasks = value;
        newTasks.forEach((task, index) => {
          if (task.isNew) {
            // Update the weight cause we will eventually
            // save this task,
            task.weight = index;
            return;
          }
          if (task.isEditing) {
            // Don't dispatch because we might have a task name change.
            return;
          }
          if (task.weight != index) {
            task.weight = index;
            this.$store.dispatch('updateTask', task);
          }
        });
        this.$store.commit('SET_TASKS', value)
      }
    },
  },

  // methods that implement data logic.
  // note there's no DOM manipulation here at all.
  methods: {
    addNewTask: function () {
      this.$store.dispatch('addNewTask');
    },
    saveTask: function (task) {
      if (this.validateTask(task, 'save')) {
        this.$store.dispatch(task.isNew ? 'createTask' : 'updateTask', task).then(this.initDeleteDialogs);
      }
    },
    deleteTask: function (task) {
      // Delete task then set all dialogs to inactive to allow drag to re-order.
      this.$store.dispatch('deleteTask', task).then(() => { this.dialogActive = false; });
    },
    markTaskAsComplete: function (task) {
      this.$store.dispatch('markTaskAsComplete', task);
    },
    cancelEditTask: function (task) {
      this.$store.dispatch('cancelEditTask', task).then(this.initDeleteDialogs);
    },
    // Init the A11yDialog behaviors.
    initDeleteDialogs: function () {
      const self = this;
      Array.prototype.forEach.call(
      document.querySelectorAll('.task__delete-dialog:not([data-task-delete-init])'), (dialogElement => {
        new A11yDialog(dialogElement)
        .on('show', function() {
          self.dialogActive = true;
        })
        .on('hide', function() {
          self.dialogActive = false;
        });
        // See selector above; Only apply to new dialog elements.
        dialogElement.setAttribute('data-task-delete-init', true);
      }));
    },
    validateTask: function(task, event = '') {
      task.errors = [];
      if (event == 'save') {
        if (!task.name) {
          task.errors.push(Drupal.t("Please add a name."));
        }
      }
      if (task.name) {
        if (task.name.length > maxCharacterLimit) {
          task.errors.push(Drupal.t("Custom goals cannot be longer than 50 characters."));
        }
      }
      // Return true/false.
      return (task.errors.length === 0);
    }
  },

  async mounted() {
    await this.$store.dispatch('fetchSessionToken');
    await this.$store.dispatch('fetchTasks');
    this.initDeleteDialogs();
    this.$nextTick(function () {
        this.isPageReady = true;
    });
  },
})

// handle routing
function onHashChange() {
  window.location.hash = ''
}

window.addEventListener('hashchange', onHashChange)
onHashChange()

// mount
app.$mount('.userTaskListApp')
