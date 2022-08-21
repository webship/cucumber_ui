/**
 * @file
 * Behaviors Cucumber UI run tests scripts.
 */

(function ($, _, Drupal, drupalSettings) {
  Drupal.behaviors.CucumberUiRunTests = {
    attach(context, settings) {
      const killProcess = function () {
        $("#cucumber-ui-kill", context).click(function () {
          $.ajax({
            url: `${drupalSettings.path.baseUrl}cucumber-ui/kill?${parseInt(
              Math.random() * 1000000000,
              10
            )}`,
            dataType: "json",
            success(data) {
              if (data.response) {
                console.log(Drupal.t("Process killed"));
                checkStatus();
              } else {
                console.log(Drupal.t("Could not kill process"));
              }
            },
            error(xhr, textStatus, error) {
              console.log(
                Drupal.t("An error happened on trying to kill the process.")
              );
            },
          });
          return false;
        });
      };

      var checkStatus = function () {
        const cucumber_ui_status = $("#cucumber-ui-status", context);
        const cucumber_ui_output = $("#cucumber-ui-output", context);

        $.ajax({
          url: `${drupalSettings.path.baseUrl}cucumber-ui/status?${parseInt(
            Math.random() * 1000000000,
            10
          )}`,
          dataType: "json",
          success(data) {
            cucumber_ui_status.removeClass("running");

            if (data.running) {
              cucumber_ui_status.addClass("running");
              cucumber_ui_status
                .find("span")
                .html(
                  `${Drupal.t("Process:") + data.pid} ${Drupal.t(
                    'Running <small><a href="#" id="cucumber-ui-kill">(kill)</a></small>'
                  )}`
                );
              killProcess();
              setTimeout(checkStatus, 10000);
            } else {
              cucumber_ui_status.find("span").html(Drupal.t("Not running"));
            }

            cucumber_ui_output.html(data.output);
          },
          error(xhr, textStatus, error) {
            console.log(
              Drupal.t("An error happened on checking tests status.")
            );
            setTimeout(checkStatus, 10000);
          },
        });
      };

      checkStatus();
      killProcess();
    },
  };
})(window.jQuery, window._, window.Drupal, window.drupalSettings);
