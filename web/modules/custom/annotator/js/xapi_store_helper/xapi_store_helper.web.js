class XAPIStoreHelper {
  getActor() {
    var actor = ADL.XAPIWrapper.lrs.actor;
    if (typeof actor === 'string') {
      return JSON.parse(actor);
    }
    return actor;
  }

  getObject() {
    return drupalSettings.Xapi.templateStatement.object;
  }

  stateId() {
    return 'annotations';
  }

  async read(callback) {
    var object = this.getObject();
    var actor = this.getActor();
    var annotations = [];
    this.state = {};
    try {
      var state = ADL.XAPIWrapper.getState(object.id, actor, this.stateId());
      annotations = state && state.highlights ? state.highlights : [];
      this.state = state;
    } catch { }
    return callback(annotations);
  }

  getAnnotationsForObjectId(object_id) {
    var actor = this.getActor();
    var annotations = [];
    this.state = {};
    try {
      var state = ADL.XAPIWrapper.getState(object_id, actor, this.stateId());
      annotations = state && state.highlights ? state.highlights : [];
      this.state = state;
    } catch { }
    return annotations;
  }

  prepareAnnotations(annotations) {
    var _this = this;
    var data = annotations.map(annotation => {
      return _this.dataForAnnotation(annotation);
    })
    return data;
  }

  dataForAnnotation(annotation) {
    var highlights = annotation.highlights;
    delete annotation.highlights;
    var data = { ...annotation };
    if (highlights) {
      annotation.highlights = highlights;
    }
    try {
      data.user = JSON.parse(annotation.user)
    } catch {}
    return data;
  };

  persist(annotations) {
    var object = this.getObject();
    this.persistForObject(object, annotations);
  }

  persistForObject(object, annotations) {
    var annotationsCopy = [...annotations];
    var actor = this.getActor();
    var state = { ...this.state };
    state.object = object;
    state.highlights = this.prepareAnnotations(annotations);
    state.date_created = (new Date(Date.now())).toISOString();
    ADL.XAPIWrapper.sendState(object.id, actor, this.stateId(), null, state);
    annotations = annotationsCopy;
    this.state = state;
  }

  generateAnnotatedStatement(annotation) {
    var actor = this.getActor();
    var verb = Xapi.verbs.annotated;
    var object = this.getObject();
    object.definition.extensions = {};
    var extension_key = `${object.id}/highlight/value`;
    object.definition.extensions[extension_key] = annotation.quote;
    var result = { 'response': annotation.text };
    var statement = new ADL.XAPIStatement(actor, verb, object, result);
    statement.generateId();
    return statement;
  }

  sendAnnotatedStatement(annotation) {
    var statement = this.generateAnnotatedStatement(annotation);
    ADL.XAPIWrapper.sendStatement(statement);
    return statement.id;
  }

  sendUpdatedStatement(annotation) {
    var void_statement = this.generateVoidedStatement(annotation);
    if (!void_statement) {
      return this.sendAnnotatedStatement(annotation);
    }
    var recreated_statement = this.generateAnnotatedStatement(annotation);
    ADL.XAPIWrapper.sendStatements([void_statement, recreated_statement]);
    return recreated_statement.id;
  }

  generateVoidedStatement(annotation) {
    var statement_id = annotation.statement_id;
    if (!statement_id) {
      return;
    }

    var actor = this.getActor();
    var verb = ADL.verbs.voided;
    var statement_ref = new ADL.XAPIStatement.StatementRef(statement_id);
    return new ADL.XAPIStatement(actor, verb, statement_ref);
  }

  sendVoidedStatement(annotation) {
    var void_statement = this.generateVoidedStatement(annotation);
    if (!void_statement) {
      return;
    }
    ADL.XAPIWrapper.sendStatement(void_statement);
  }

  getObjectName(object) {
    var names = object.definition.name;
    var language = window.navigator.userLanguage || window.navigator.language;
    if (!names || !language) {
      return;
    }

    if (names['en'] || names[language]) {
      return names['en'] || names[language];
    }

    var language = language.split("-")[0];
    return names[language];
  }
}
