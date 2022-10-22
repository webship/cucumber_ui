/**
 * @file
 * Behaviors Cucumber UI New test scripts.
 */

(function ($, _, Drupal, drupalSettings) {
  Drupal.behaviors.CucumberUiNewTest = {
    attach(context, settings) {
      // Keep cursor in position after updating textarea
      // Reference: http://stackoverflow.com/questions/13949059/persisting-the-changes-of-range-objects-after-selection-in-html/13950376#13950376
      let saveSelection;
      let restoreSelection;

      if (window.getSelection && document.createRange) {
        saveSelection = function (containerEl) {
          const range = window.getSelection().getRangeAt(0);
          const preSelectionRange = range.cloneRange();
          preSelectionRange.selectNodeContents(containerEl);
          preSelectionRange.setEnd(range.startContainer, range.startOffset);
          const start = preSelectionRange.toString().length;

          return {
            start,
            end: start + range.toString().length,
          };
        };

        restoreSelection = function (containerEl, savedSel) {
          let charIndex = 0;
          const range = document.createRange();
          range.setStart(containerEl, 0);
          range.collapse(true);
          const nodeStack = [containerEl];
          let node;
          let foundStart = false;
          let stop = false;

          while (!stop && (node = nodeStack.pop())) {
            if (node.nodeType == 3) {
              const nextCharIndex = charIndex + node.length;
              if (
                !foundStart &&
                savedSel.start >= charIndex &&
                savedSel.start <= nextCharIndex
              ) {
                range.setStart(node, savedSel.start - charIndex);
                foundStart = true;
              }
              if (
                foundStart &&
                savedSel.end >= charIndex &&
                savedSel.end <= nextCharIndex
              ) {
                range.setEnd(node, savedSel.end - charIndex);
                stop = true;
              }
              charIndex = nextCharIndex;
            } else {
              let i = node.childNodes.length;
              while (i--) {
                nodeStack.push(node.childNodes[i]);
              }
            }
          }

          const sel = window.getSelection();
          sel.removeAllRanges();
          sel.addRange(range);
        };
      } else if (document.selection) {
        saveSelection = function (containerEl) {
          const selectedTextRange = document.selection.createRange();
          const preSelectionTextRange = document.body.createTextRange();
          preSelectionTextRange.moveToElementText(containerEl);
          preSelectionTextRange.setEndPoint('EndToStart', selectedTextRange);
          const start = preSelectionTextRange.text.length;

          return {
            start,
            end: start + selectedTextRange.text.length,
          };
        };

        restoreSelection = function (containerEl, savedSel) {
          const textRange = document.body.createTextRange();
          textRange.moveToElementText(containerEl);
          textRange.collapse(true);
          textRange.moveEnd('character', savedSel.end);
          textRange.moveStart('character', savedSel.start);
          textRange.select();
        };
      }

      // Replace step fields by rich text fields.
      const syntaxHighlight = function (text) {
        return text
          .replace(
            /((\([^\)]*\))|(( |(&nbsp;))[0-9]+( |(&nbsp;))))/g,
            "<span class='step-param'>$1</span>",
          )
          .replace(
            /"([a-zA-Z0-9\[\]_\-:\/\. ]+)"/g,
            '"<span class=\'step-param\'>$1</span>"',
          )
          .replace(/([\|:])\|/g, '$1<br />|')
          .replace(/\|(.*)\|/, '<pre class="step-param">|$1|</pre>')
          .replace(/\|/g, '<span class="step-no-param">|</span>');
      };

      // Sort steps.
      const sortfunction = function (link, direction) {
        let appendfunction;
        let selectfunction;

        if (direction === 'up') {
          appendfunction = 'before';
          selectfunction = 'prev';
        } else if (direction === 'down') {
          appendfunction = 'after';
          selectfunction = 'next';
        } else {
          return false;
        }

        const $current = $(link).closest('.form-wrapper');
        const $other = $current[selectfunction]('.form-wrapper');

        if ($other.length) {
          $other[appendfunction]($current);

          // Rename fields, otherwise it won't make any difference when form is submitted.
          const currenttextname = $current.find('.form-text').attr('name');
          const currentselectname = $current.find('.form-select').attr('name');
          $current
            .find('.form-text')
            .attr('name', $other.find('.form-text').attr('name'));
          $current
            .find('.form-select')
            .attr('name', $other.find('.form-select').attr('name'));
          $other.find('.form-text').attr('name', currenttextname);
          $other.find('.form-select').attr('name', currentselectname);
        }

        return false;
      };
    },
  };

  Drupal.behaviors.enrichStepFields = {
    attach(context, settings) {
      $(
        '#cucumber-ui-new-steps .form-text:not(.form-rich-processed)',
        context,
      ).each(function () {
        const id = `${$(this).attr('id')}-rich`;
        const $rich = $(
          `<div id="${id}" contenteditable="true" class="form-rich step-no-param" />`,
        );
        const $plain = $(this);
        const $wrapper = $(
          `<div id="${id}-wrapper" class="field-rich-wrapper" />`,
        );

        $plain.after($rich);
        $plain.addClass('form-rich-processed');
        $rich.html(syntaxHighlight($plain.val()));
        $([$plain[0], $rich[0]]).wrapAll($wrapper);
        $rich.parents('.field-rich-wrapper').height($rich.height() - 2);

        $rich.keyup(function () {
          const text = $(this).text();
          const savedSel = saveSelection(this);
          $(this)
            .parents('.field-rich-wrapper')
            .height($(this).height() - 2);
          $plain.val(text);
          $(this).html(syntaxHighlight(text));
          $(this).focus();
          restoreSelection(this, savedSel);
          // In order to trigger auto-complete.
          $plain.keyup();
        });

        // Steps should be sortable and removable.
        const $actions = $('<div class="step-actions" />');
        const $sortup = $('<a href="#" class="sort-up"></a>')
          .attr('title', Drupal.t('Move this step up'))
          .html(Drupal.t('Up'));
        const $sortdown = $('<a href="#" class="sort-down"></a>')
          .attr('title', Drupal.t('Move this step down'))
          .html(Drupal.t('Down'));
        const $remove = $('<a href="#" class="remove"></a>')
          .attr('title', Drupal.t('Remove this step'))
          .html(Drupal.t('Remove'));

        $actions.append($sortup).append($sortdown).append($remove);
        $(this)
          .closest('.fieldset-wrapper')
          .find('.form-type-select')
          .before($actions);

        $sortup.click(function () {
          return sortfunction(this, 'up');
        });
        $sortdown.click(function () {
          return sortfunction(this, 'down');
        });

        $remove.click(function () {
          $(this).closest('.form-wrapper').remove();
          return false;
        });
      });
    },
  };
})(window.jQuery, window._, window.Drupal, window.drupalSettings);
