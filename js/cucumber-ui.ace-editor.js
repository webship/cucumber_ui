/**
 * @file
 * Cucumber UI Ace editor.
 *
 * Binds the Ace editor to the Gherkin textarea of the create test screen and
 * feeds it the step definitions of the site. No jQuery: the Cucumber family
 * drives its behaviour with HTMX and plain DOM APIs.
 */

((Drupal, once) => {
  /**
   * The Gherkin keywords offered before any step definition is fetched.
   *
   * @type {Array.<string>}
   */
  const keywords = [
    'Feature: ',
    'Background: ',
    'Scenario: ',
    'Outline ',
    'Examples ',
    'Given ',
    'When ',
    'Then',
    'And ',
    'But',
    '@javascript ',
    '@api ',
    '@check ',
    '@local ',
    '@development ',
    '@staging ',
    '@production ',
    '@init ',
    '@cleanup ',
  ];

  /**
   * The step definitions of the site, fetched once and then reused.
   *
   * @type {?Promise.<Array.<string>>}
   */
  let stepDefinitions = null;

  /**
   * Fetches the step definitions of the site.
   *
   * @return {Promise.<Array.<string>>}
   *   The list of step definitions, empty when the request fails.
   */
  function fetchStepDefinitions() {
    if (stepDefinitions === null) {
      stepDefinitions = fetch(
        Drupal.url('admin/config/development/cucumber-ui/cucumber-dl-json'),
        { headers: { Accept: 'application/json' } },
      )
        .then((response) => (response.ok ? response.json() : []))
        .catch(() => []);
    }

    return stepDefinitions;
  }

  /**
   * Turns a list of strings into Ace completions.
   *
   * @param {Array.<string>} values
   *   The completion values.
   * @param {string} prefix
   *   The text already typed, prepended to every value.
   *
   * @return {Array.<object>}
   *   The Ace completions.
   */
  function toCompletions(values, prefix) {
    return values.map((value) => ({
      name: prefix + value,
      value: prefix + value,
    }));
  }

  /**
   * Attaches the Ace editor to the Gherkin textarea.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Starts the editor and keeps the textarea in sync with it.
   */
  Drupal.behaviors.cucumberUiAceEditor = {
    attach(context) {
      if (typeof ace === 'undefined' || typeof ace.edit !== 'function') {
        return;
      }

      once('cucumber-ui-ace-editor', '#free_text_ace_editor', context).forEach(
        (element) => {
          const textarea = document.querySelector('.free-text-ace-editor');
          if (!textarea) {
            return;
          }

          const editor = ace.edit(element);
          const session = editor.getSession();

          session.setMode('ace/mode/gherkin');
          session.setTabSize(2);

          editor.setOption('autoScrollEditorIntoView', 'always');
          editor.setOption('mergeUndoDeltas', 'always');
          editor.setOption('hScrollBarAlwaysVisible', true);
          editor.setOption('vScrollBarAlwaysVisible', true);

          ace.require('ace/ext/keybinding_menu');
          editor.commands.addCommand({
            name: 'showKeyboardShortcuts',
            bindKey: { win: 'Ctrl-Alt-h', mac: 'Command-Alt-h' },
            exec(currentEditor) {
              ace.config.loadModule('ace/ext/keybinding_menu', (module) => {
                module.init(currentEditor);
                currentEditor.showKeyboardShortcuts();
              });
            },
          });

          const languageTools = ace.require('ace/ext/language_tools');

          languageTools.setCompleters([
            {
              getCompletions(currentEditor, currentSession, pos, prefix, done) {
                if (!['Given', 'When', 'Then', 'And', 'But'].includes(prefix)) {
                  done(null, []);
                  return;
                }
                fetchStepDefinitions().then((steps) => {
                  done(null, toCompletions(steps, prefix));
                });
              },
            },
            {
              getCompletions(currentEditor, currentSession, pos, prefix, done) {
                done(null, toCompletions(keywords, ''));
              },
            },
          ]);

          editor.setOptions({ enableBasicAutocompletion: true });

          session.setValue(textarea.value);
          session.on('change', () => {
            textarea.value = session.getValue();
          });

          // When the form fails to validate because the textarea is required,
          // shift the focus to the editor.
          textarea.addEventListener('focus', () => {
            editor.focus();
          });
        },
      );
    },
  };
})(Drupal, once);
