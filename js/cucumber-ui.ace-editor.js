/**
 * @file
 * Cucumber UI Ace Editor.
 */

(function ($, Drupal) {
  Drupal.behaviors.cucumberUiAceEditor = {
    attach() {
      if (typeof ace === 'undefined' || typeof ace.edit !== 'function') {
        return;
      }

      const editor = ace.edit('free_text_ace_editor');
      editor.getSession().setMode('ace/mode/gherkin');
      editor.getSession().setTabSize(2);

      editor.setOption('autoScrollEditorIntoView', 'always');
      editor.setOption('mergeUndoDeltas', 'always');
      editor.setOption('hScrollBarAlwaysVisible', true);
      editor.setOption('vScrollBarAlwaysVisible', true);

      // Add command to lazy-load keybinding_menu extension.
      ace.require('ace/ext/keybinding_menu');
      editor.commands.addCommand({
        name: 'showKeyboardShortcuts',
        bindKey: { win: 'Ctrl-Alt-h', mac: 'Command-Alt-h' },
        exec(editor) {
          ace.config.loadModule('ace/ext/keybinding_menu', function (module) {
            module.init(editor);
            editor.showKeyboardShortcuts();
          });
        },
      });

      const cucumberUiLanguageTools = ace.require('ace/ext/language_tools');

      editor.setOptions({
        enableBasicAutocompletion: false,
      });

      const cucumberUICompleter = {
        getCompletions(editor, session, pos, prefix, callback) {
          const gList = ['Given', 'When', 'Then', 'And', 'But'];

          if (prefix.length === 0) {
            callback(null, []);
            return;
          }
          if (!gList.includes(prefix)) {
            callback(null, []);
            return;
          }

          $.getJSON(
            Drupal.url('admin/config/development/cucumber-ui/cucumber-dl-json'),
            function (cucumberUiList) {
              callback(
                null,
                cucumberUiList.map(function (cucumberUiItem) {
                  return {
                    name: prefix + cucumberUiItem,
                    value: prefix + cucumberUiItem,
                  };
                }),
              );
            },
          );
        },
      };

      const keyCucumberList = [
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

      const keyCucumberUICompleter = {
        getCompletions(editor, session, pos, prefix, callback) {
          callback(
            null,
            keyCucumberList.map(function (keyCucumberUiItem) {
              return {
                name: keyCucumberUiItem,
                value: keyCucumberUiItem,
              };
            }),
          );
        },
      };

      cucumberUiLanguageTools.setCompleters([
        cucumberUICompleter,
        keyCucumberUICompleter,
      ]);

      editor.setOptions({
        enableBasicAutocompletion: true,
      });

      editor.getSession().on('change', function () {
        $('.free-text-ace-editor').val(editor.getSession().getValue());
      });

      editor.getSession().setValue($('.free-text-ace-editor').val());

      // When the form fails to validate because the text area is required,
      // shift the focus to the editor.
      $('.free-text-ace-editor').on('focus', function () {
        editor.getSession().textInput.focus();
      });
    },
  };
})(jQuery, Drupal);
