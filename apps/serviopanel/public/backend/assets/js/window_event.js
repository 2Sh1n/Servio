"use strict";
$(document).on("submit", ".update-form-submit-event", function (e) {
  e.preventDefault();
  var formData = new FormData(this);
  var form_id = $(this).attr("id");
  var error_box = $("#error_box", this);
  var submit_btn = $(this).find(".submit_btn");
  var btn_html = $(this).find(".submit_btn").html();
  var btn_val = $(this).find(".submit_btn").val();
  var button_text =
    btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;
  formData.append(csrfName, csrfHash);
  $.ajax({
    type: "POST",
    url: $(this).attr("action"),
    data: formData,
    cache: false,
    contentType: false,
    processData: false,
    dataType: "json",
    beforeSend: function () {
      submit_btn.html("Please Wait..");
      submit_btn.attr("disabled", true);
    },
    success: function (response) {
      csrfName = response["csrfName"];
      csrfHash = response["csrfHash"];
      if (response.error == false) {
        iziToast.success({
          title: "",
          message: response.message,
          position: "topRight",
        });
        submit_btn.attr("disabled", false);
        submit_btn.html(button_text);
        $(".close").click();
        $("#user_list").bootstrapTable("refresh");
        $("#slider_list").bootstrapTable("refresh");
        $("#update_modal").bootstrapTable("refresh");
        setTimeout(() => {
          window.location.reload();
        }, 3000);
      } else {
        if (
          typeof response.message === "object" &&
          !Array.isArray(response.message) &&
          response.message !== null
        ) {
          for (var k in response.message) {
            if (response.message.hasOwnProperty(k)) {
              showToastMessage(response.message[k], "error");
            }
          }
        } else {
          showToastMessage(response.message, "error");
        }
        submit_btn.attr("disabled", false);
        submit_btn.html(button_text);
        setTimeout(() => {
          $("#update_modal").bootstrapTable("refresh");
        }, 5000);
      }
    },
  });
});
window.user_events = {
  "click .deactivate_user": function (e, value, row, index) {
    var user_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_deactivate_this_user,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        user_id: user_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/users/deactivate",
          data: input_body,
          dataType: "json",
          success: function (response) {
            csrfName = response["csrfName"];
            csrfHash = response["csrfHash"];
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#user_list").bootstrapTable("refresh");
              }, 5000);
            } else {
              setTimeout(() => {
                $("#user_list").bootstrapTable("refresh");
              }, 5000);
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .activate_user": function (e, value, row, index) {
    var user_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_activate_this_user,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        user_id: user_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/users/activate",
          data: input_body,
          dataType: "json",
          success: function (response) {
            csrfName = response["csrfName"];
            csrfHash = response["csrfHash"];
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#user_list").bootstrapTable("refresh");
              }, 5000);
            } else {
              setTimeout(() => {
                $("#user_list").bootstrapTable("refresh");
              }, 5000);
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .delete-user": function (e, value, row, index) {
    var user_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_delete_this_user,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        user_id: user_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/system_users/delete_user",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#partner_list").bootstrapTable("refresh");
              }, 5000);
              window.location.reload();
              return;
            } else {
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .edit-user": function (e, value, row, index) {
    $("#id").val(row.id);
    // Populate user information fields
    $("#edit_username").val(row.username || "");
    $("#edit_email").val(row.email || "");
    $("#edit_phone").val(row.phone || "");
    $(document).ready(function () {
      $("#edit_role").val(row.role_a).trigger("change");
      if ($("#edit_role").val() == 1) {
        $("#permissions").hide();
      } else {
        $("#permissions").show();
      }
    });
    // Safely parse permissions.
    // Sometimes backend sends "undefined" / null / empty string.
    // JSON.parse("undefined") throws: `"undefined" is not valid JSON`.
    var permissions = null;
    try {
      if (
        row.permissions &&
        row.permissions !== "null" &&
        row.permissions !== "undefined"
      ) {
        permissions =
          typeof row.permissions === "string"
            ? JSON.parse(row.permissions)
            : row.permissions;
      }
    } catch (e) {
      // If parsing fails, log the bad value and continue with no permissions.
      console.error("Invalid permissions JSON:", row.permissions, e);
      permissions = null;
    }
    let values;
    var data = permissions != null ? true : false;
    if (data) {
      Object.keys(permissions).forEach((key) => {
        let single_object = permissions[key];
        if (key == "create") {
          Object.keys(single_object).forEach((val) => {
            if (single_object.order == 0) {
              let order = $("#orders_create_edit")[0];
              $(order).attr("checked", true);
            }
            if (single_object.category == 1) {
              let category = $("#categories_create_edit")[0];
              $(category).attr("checked", true);
            }
            if (single_object.subscription == 1) {
              let subscription = $("#subscription_create_edit")[0];
              $(subscription).attr("checked", true);
            }
            if (single_object.sliders == 1) {
              let object = $("#sliders_create_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.tax == 1) {
              let object = $("#tax_create_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.sliders == 1) {
              let object = $("#sliders_create_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.services == 1) {
              let object = $("#services_create_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.promo_code == 1) {
              let object = $("#promo_code_create_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.featured_section == 1) {
              let object = $("#featured_section_create_edit")[0];
              $(object).attr("checked", true);
            } //
            if (
              single_object.partner == 1 ||
              single_object.partner != undefined
            ) {
              let object = $("#partner_create_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.notification == 1) {
              let object = $("#send_notification_create_edit")[0];
              $(object).attr("checked", true);
            } //
            if (single_object.email_notifications == 1) {
              // Email notifications need their own toggle so admins can limit mail access.
              let emailObject = $("#email_notifications_create_edit")[0];
              $(emailObject).attr("checked", true);
            }
            if (single_object.faq == 1) {
              let object = $("#faq_create_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.settings == 1) {
              let object = $("#settings_create_edit")[0];
              $(object).attr("checked", true);
            }
          });
        } else if (key == "read") {
          Object.keys(single_object).forEach((val) => {
            if (single_object.order == 0) {
              let object = $("#orders_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.category == 1) {
              let object = $("#categories_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.subscription == 1) {
              let object = $("#subscription_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.sliders == 1) {
              let object = $("#sliders_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.tax == 1) {
              let object = $("#tax_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.services == 1) {
              let object = $("#services_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.promo_code == 1) {
              let object = $("#promo_code_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.featured_section == 1) {
              let object = $("#featured_section_read_edit")[0];
              $(object).attr("checked", true);
            } //
            if (
              single_object.partner == 1 ||
              single_object.partner != undefined
            ) {
              let object = $("#partner_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (
              single_object.customer != undefined &&
              single_object.customer == 1
            ) {
              let object = $("#customers_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.notification == 1) {
              let object = $("#send_notification_read_edit")[0];
              $(object).attr("checked", true);
            } //
            if (single_object.email_notifications == 1) {
              // Keep read access to the email page in sync with stored permissions.
              let emailObject = $("#email_notifications_read_edit")[0];
              $(emailObject).attr("checked", true);
            }
            if (single_object.faq == 1) {
              let object = $("#faq_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.settings == 1) {
              let object = $("#settings_read_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.system == 1) {
              let object = $("#system_user_read_edit")[0];
              $(object).attr("checked", true);
            }
          });
        } else if (key == "update") {
          Object.keys(single_object).forEach((val) => {
            if (single_object.order == 0) {
              let object = $("#orders_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.category == 1) {
              let object = $("#categories_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.subscription == 1) {
              let object = $("#subscription_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.sliders == 1) {
              let object = $("#sliders_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.tax == 1) {
              let object = $("#tax_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.services == 1) {
              let object = $("#services_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.promo_code == 1) {
              let object = $("#promo_code_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.featured_section == 1) {
              let object = $("#featured_section_update_edit")[0];
              $(object).attr("checked", true);
            } //
            if (
              single_object.partner == 1 ||
              single_object.partner != undefined
            ) {
              let object = $("#partner_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (
              single_object.customer != undefined &&
              single_object.customer == 1
            ) {
              let object = $("#customers_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.notification == 1) {
              let object = $("#send_notification_update_edit")[0];
              $(object).attr("checked", true);
            } //
            if (single_object.email_notifications == 1) {
              // Update guard ensures only authorised users can modify email templates.
              let emailObject = $("#email_notifications_update_edit")[0];
              $(emailObject).attr("checked", true);
            }
            if (single_object.faq == 1) {
              let object = $("#faq_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.system == 1) {
              let object = $("#system_update_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.settings == 1) {
              let object = $("#settings_update_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.system_user == 1) {
              let object = $("#system_user_update_edit")[0];
              $(object).attr("checked", true);
            }
          });
        } else if (key == "delete") {
          Object.keys(single_object).forEach((val) => {
            if (single_object.order == 0) {
              let object = $("#orders_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.category == 1) {
              let object = $("#categories_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.subscription == 1) {
              let object = $("#subscription_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.sliders == 1) {
              let object = $("#sliders_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.tax == 1) {
              let object = $("#tax_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.services == 1) {
              let object = $("#services_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.promo_code == 1) {
              let object = $("#promo_code_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.featured_section == 1) {
              let object = $("#featured_section_delete_edit")[0];
              $(object).attr("checked", true);
            } //
            if (
              single_object.partner == 1 ||
              single_object.partner != undefined
            ) {
              let object = $("#partner_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (
              single_object.customer != undefined &&
              single_object.customer == 1
            ) {
              let object = $("#customers_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.notification == 1) {
              let object = $("#send_notification_delete_edit")[0];
              $(object).attr("checked", true);
            } //
            if (single_object.email_notifications == 1) {
              // Delete flag mirrors other actions so the modal stays predictable.
              let emailObject = $("#email_notifications_delete_edit")[0];
              $(emailObject).attr("checked", true);
            }
            if (single_object.faq == 1) {
              let object = $("#faq_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.system == 1) {
              let object = $("#system_update_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.settings == 1) {
              let object = $("#settings_delete_edit")[0];
              $(object).attr("checked", true);
            }
            if (single_object.system_user == 1) {
              let object = $("#system_user_delete_edit")[0];
              $(object).attr("checked", true);
            }
          });
        }
      });
    }
  },
};
$("#type_1").on("change", function () {
  // hide all first
  $("#categories_select_1, #services_select_1, #edit_url_section")
    .addClass("d-none");

  let val = $(this).val();

  if (val === "provider") {
    $("#services_select_1").removeClass("d-none");
  }
  else if (val === "Category") {
    $("#categories_select_1").removeClass("d-none");
  }
  else if (val === "url") {
    $("#edit_url_section").removeClass("d-none");
  }
});

let source = "";
window.slider_events = {
  "click .edite-slider": function (e, value, row, index) {

    // Set the ID and basic values first
    $("#id").val(row.id);

    // Use original_type (raw database value) instead of translated type
    var originalType = row.original_type || row.type;

    // Set the type and trigger change event to show appropriate fields
    $("#type_1").val(originalType).trigger("change");

    // Now set the specific field values based on type
    if (originalType == "provider") {
      $("#service_item_1").val(row.type_id).trigger("change");
    }
    if (originalType == "Category") {
      $("#Category_item_1").val(row.type_id).trigger("change");
    }
    if (originalType == "url") {
      $("#edit_slider_url").val(row.url);
    }

    // Handle image display
    var regex = /<img.*?src="(.*?)"/;
    var app_image_src = regex.exec(row.slider_app_image)[1];
    var web_image_src = regex.exec(row.slider_web_image)[1];
    source = app_image_src;
    $("#offer_image").attr("src", app_image_src);
    $("#offer_web_image").attr("src", web_image_src);

    // Handle status switch with delay to ensure proper initialization
    setTimeout(function () {
      if (row.og_status == "1") {
        $(".editInModel").prop("checked", false).trigger("click");
      } else {
        $(".editInModel").prop("checked", true).trigger("click");
      }
    }, 600);
  },
  "click .delete-slider": function (e, value, row, index) {
    var users_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/admin/sliders/delete_sliders",
          {
            [csrfName]: csrfHash,
            user_id: users_id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              $("#slider_list").bootstrapTable("refresh");
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
};
$(document).ready(function () {
  $("#edit_section_type").change(function (e) {
    e.preventDefault();
    const sections = {
      partners: ".edit_partners_ids",
      categories: ".edit_category_item",
      top_rated_partner: ".edit_top_rated_providers",
      previous_order: ".edit_previous_order",
      ongoing_order: ".edit_ongoing_order",
      near_by_provider: ".edit_near_by_providers",
      banner: ".edit_banner_section",
    };
    // Get the selected value from the dropdown
    const selectedSection = $(this).val();
    // Hide all sections
    $(
      ".edit_category_item, .edit_partners_ids, .edit_top_rated_providers, .edit_previous_order, .edit_ongoing_order, .edit_near_by_providers,.edit_banner_section"
    ).addClass("d-none");
    // Reset banner sub-type fields when changing away from banner
    if (selectedSection != "banner") {
      // Hide all banner sub-type fields first
      $("#edit_banner_providers_select").hide();
      $("#edit_banner_categories_select").hide();
      $("#edit_banner_url_section").hide();
      // Reset banner_type select
      $("#edit_banner_type").val("").trigger("change");
      // Clear banner sub-type field values
      if ($("#edit_category_item").hasClass("select2-hidden-accessible")) {
        $("#edit_category_item").val(null).trigger("change.select2");
      } else {
        $("#edit_category_item").val("").trigger("change");
      }
      if ($("#edit_banner_providers").hasClass("select2-hidden-accessible")) {
        $("#edit_banner_providers").val(null).trigger("change.select2");
      } else {
        $("#edit_banner_providers").val("").trigger("change");
      }
      $("#edit_banner_url").val("");
    }
    if (sections[selectedSection]) {
      $(sections[selectedSection]).removeClass("d-none");
    }
  });
  $(
    "#edit_banner_providers_select,#edit_banner_categories_select,#edit_banner_url_section"
  ).hide();
  $("#edit_banner_type").on("change", function () {
    if ($("#edit_banner_type").val() == "banner_default") {
      $("#edit_banner_providers_select").hide();
      $("#edit_banner_categories_select").hide();
      $("#edit_banner_url_section").hide();
    }
    if ($("#edit_banner_type").val() == "banner_provider") {
      $("#edit_banner_providers_select").show();
      $("#edit_banner_categories_select").hide();
      $("#edit_banner_url_section").hide();
    } else if ($("#edit_banner_type").val() == "banner_category") {
      $("#edit_banner_providers_select").hide();
      $("#edit_banner_categories_select").show();
      $("#edit_banner_url_section").hide();
    } else if ($("#edit_banner_type").val() == "banner_url") {
      $("#edit_banner_providers_select").hide();
      $("#edit_banner_categories_select").hide();
      $("#edit_banner_url_section").show();
    } else {
      $("#edit_banner_providers_select").hide();
      $("#edit_banner_categories_select").hide();
      $("#edit_banner_url_section").hide();
    }
  });
});
window.featured_section_events = {
  "click .delete-featured_section": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/admin/featured_sections/delete_featured_section",
          {
            [csrfName]: csrfHash,
            id: id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $("#user_list").bootstrapTable("refresh");
              }, 2000);
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
  "click .update_featured_section": function (e, value, row, index) {
    var sectionId = row.id;

    // Fetch section data with translations
    $.post(
      baseUrl + "/admin/featured_sections/get_section_data",
      {
        [csrfName]: csrfHash,
        id: sectionId,
      },
      function (data) {
        if (data.error == false) {
          // Debug logging to help verify that click handler runs
          // and that backend sends expected image URLs.
          // console.log("Featured section edit response:", data);

          var sectionData = data.data.section_data || {};
          var translations = data.data.translations;

          // Handle app banner image preview
          // The backend now returns direct image URLs (or default image if file doesn't exist)
          // So we can use the URL directly without regex extraction
          // NOTE: We convert to string and trim defensively to avoid JS errors when value is null/undefined
          var appImageUrl = "";
          if (sectionData && sectionData.app_banner_image) {
            appImageUrl = String(sectionData.app_banner_image).trim();
          }

          // console.log("App banner image URL (resolved):", appImageUrl);

          if (appImageUrl !== "") {
            // Set the image source directly from the URL returned by backend
            // Backend uses get_file_url() which returns default image if file is missing
            $("#preview_app_image").attr("src", appImageUrl);
          } else {
            // Fallback to default image if no URL is provided
            $("#preview_app_image").attr("src", baseUrl + "public/backend/assets/default.png");
          }

          // Handle web banner image preview
          // The backend now returns direct image URLs (or default image if file doesn't exist)
          // So we can use the URL directly without regex extraction
          var webImageUrl = "";
          if (sectionData && sectionData.web_banner_image) {
            webImageUrl = String(sectionData.web_banner_image).trim();
          }

          // console.log("Web banner image URL (resolved):", webImageUrl);

          if (webImageUrl !== "") {
            // Set the image source directly from the URL returned by backend
            // Backend uses get_file_url() which returns default image if file is missing
            $("#preview_banner_image").attr("src", webImageUrl);
          } else {
            // Fallback to default image if no URL is provided
            $("#preview_banner_image").attr("src", baseUrl + "public/backend/assets/default.png");
          }

          var category, partner, i, previous_order;
          $("#id").val(sectionData.id);

          // Populate multilingual title and description fields
          // Use languages array from response to ensure we match HTML field IDs exactly
          // The HTML uses language code directly: edit_title<?= $language['code'] ?>
          // Iterate through languages array to ensure we only set values for languages that exist in HTML
          var languages = data.data.languages || [];
          if (translations && languages.length > 0) {
            languages.forEach(function (language) {
              var languageCode = language.code;
              var translation = translations[languageCode];

              // Only set values if translation data exists for this language
              if (translation) {
                // Use language code exactly as it comes from backend to match HTML field IDs
                // Check if element exists before setting value (defensive programming)
                var titleField = $("#edit_title" + languageCode);
                var descriptionField = $("#edit_description" + languageCode);

                if (titleField.length > 0) {
                  titleField.val(translation.title || '');
                }

                if (descriptionField.length > 0) {
                  descriptionField.val(translation.description || '');
                }
              }
            });
          }

          // Always show title and description fields for multilingual support
          //  $(".edit_title").removeClass("d-none");
          if (sectionData.status == "1") {
            $("#edit_status_active").prop("checked", true);
          } else {
            $("#edit_status_deactive").prop("checked", true);
          }
          $("#edit_section_type").val(sectionData.section_type).trigger("change");
          const sections = {
            partners: ".partners_ids",
            categories: ".Category_item",
            top_rated_partner: ".top_rated_providers",
            previous_order: ".previous_order",
            ongoing_order: ".ongoing_order",
            near_by_provider: ".near_by_providers",
            banner: ".edit_banner_section",
          };
          const selectedSection = $("#edit_section_type").val();
          $(
            ".Category_item, .partners_ids, .top_rated_providers, .previous_order, .ongoing_order, .near_by_providers,.edit_banner_section"
          ).addClass("d-none");
          if (sections[selectedSection]) {
            $(sections[selectedSection]).removeClass("d-none");
          }
          setTimeout(function () {
            if (sectionData.status == "1") {
              $(".editInModel").prop("checked", false).trigger("click");
            } else {
              $(".editInModel").prop("checked", true).trigger("click");
            }
          }, 600);

          // console.log(sectionData.section_type);

          if (sectionData.section_type == "categories") {
            category = sectionData.category_ids.split(",");
            var value_given = sectionData.category_ids.split(",");
            $(document).ready(function () {
              $("#edit_Category_item").val(sectionData.category_ids.split(",")).select2({
                placeholder: "Select Categories",
              });
            });
          } else if (sectionData.section_type == "previous_order") {
            $("#edit_previoud_order_limit").val(sectionData.limit);
          } else if (sectionData.section_type == "ongoing_order") {
            $("#edit_ongoing_order_limit").val(sectionData.limit);
          } else if (sectionData.section_type == "near_by_provider") {
            $("#edit_limit_for_near_by_providers").val(sectionData.limit);
          }
          else if (sectionData.section_type == "top_rated_partner") {
            $("#edit_top_rated_providers").val(sectionData.limit);
          }
          else if (sectionData.section_type == "banner") {
            $("#edit_title").val();
            $(".edit_title").hide();
            $("#edit_banner_type").val(sectionData.banner_type).trigger("change");
            if (sectionData.banner_type == "banner_default") {
              $("#edit_banner_categories_select").hide();
              $("#edit_banner_providers_select").hide();
              $("#edit_banner_url_section").hide();
            } else if (sectionData.banner_type == "banner_category") {
              $("#edit_banner_categories_select").show();
              $("#edit_category_item")
                .val(sectionData.category_ids.split(","))
                .select2({ placeholder: "Select Categories" });
              $("#edit_banner_providers_select").hide();
              $("#edit_banner_url_section").hide();
            } else if (sectionData.banner_type == "banner_provider") {
              $("#edit_banner_providers_select").show();
              $("#edit_banner_providers")
                .val(sectionData.partners_ids.split(","))
                .select2({ placeholder: "Select Provider" });
              $("#edit_banner_categories_select").hide();
              $("#edit_banner_url_section").hide();
            } else if (sectionData.banner_type == "banner_url") {
              $("#edit_banner_categories_select").hide();
              $("#edit_banner_providers_select").hide();
              $("#edit_banner_url_section").show();
              $("#edit_banner_url").val(sectionData.banner_url);
            }
          } else {
            if (sectionData.partners_ids != null) {
              partner = sectionData.partners_ids.split(",");
              parseInt(sectionData.partners_ids);
            }
            $(document).ready(function () {
              $("#edit_partners_ids").val(partner).select2({
                placeholder: "Select Providers",
              });
            });
          }
        } else {
          // Handle error
          showToastMessage("Failed to load section data", "error");
        }
      }
    ).fail(function () {
      // Handle AJAX failure
      showToastMessage("Failed to load section data", "error");
    });
  },
};
// $(document).ready(function () {
//   $("#edit_section_type").on("change", function () {
//     if ($(this).val() == "categories") {
//       $(".edit_category_item").removeClass("d-none");
//       $(".edit_partners_ids").addClass("d-none");
//       $(".edit_previous_order").addClass("d-none");
//       $(".edit_ongoing_order").addClass("d-none");
//     } else if ($(this).val() == "partner" || $(this).val() == "partners") {
//       $(".edit_category_item").addClass("d-none");
//       $(".edit_partners_ids").removeClass("d-none");
//       $(".edit_previous_order").addClass("d-none");
//       $(".edit_ongoing_order").addClass("d-none");
//     } else if ($(this).val() == "top_rated_partner" || $(this).val() == "top_rated_service") {
//       $(".edit_partners_ids").addClass("d-none");
//       $(".edit_category_item").addClass("d-none");
//       $(".edit_previous_order").addClass("d-none");
//       $(".edit_ongoing_order").addClass("d-none");
//     } else if ($(this).val() == "previous_order") {
//       $(".edit_partners_ids").addClass("d-none");
//       $(".edit_category_item").addClass("d-none");
//       $(".edit_previous_order").removeClass("d-none");
//       $(".edit_ongoing_order").addClass("d-none");
//     } else if ($(this).val() == "ongoing_order") {
//       $(".edit_partners_ids").addClass("d-none");
//       $(".edit_category_item").addClass("d-none");
//       $(".edit_previous_order").addClass("d-none");
//       $(".edit_ongoing_order").removeClass("d-none");
//     } else {
//       $(".edit_partners_ids").addClass("d-none");
//       $(".edit_category_item").addClass("d-none");
//       $(".edit_previous_order").addClass("d-none");
//       $(".edit_ongoing_order").addClass("d-none");
//     }
//   });
// });
window.promo_codes_events = {
  "click .delete-promo_codes": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        var input_body = {
          [csrfName]: csrfHash,
          id: id,
        };
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/promo_codes/delete",
          data: input_body,
          dataType: "json",
          success: function (response) {
            csrfName = response["csrfName"];
            csrfHash = response["csrfHash"];
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#promo_code_list").bootstrapTable("refresh");
              }, 2000);
            } else {
              setTimeout(() => {
                $("#promo_code_list").bootstrapTable("refresh");
              }, 2000);
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .edit": function (e, value, row, index) {
    e.preventDefault();

    // Get promocode data with translations
    var input_body = {
      [csrfName]: csrfHash,
      id: row.id,
    };

    $.ajax({
      type: "POST",
      url: baseUrl + "admin/promo_codes/get_promocode_data",
      data: input_body,
      dataType: "json",
      success: function (response) {
        csrfName = response["csrfName"];
        csrfHash = response["csrfHash"];

        if (response.error == false) {
          var promocode = response.data.promocode;
          var translations = response.data.translations;

          // Set basic promocode fields
          $('input[name="promo_id"]').val(promocode.id);
          $('input[name="promo_code"]').val(promocode.promo_code);

          // Set partner value - the dropdown already has correct display_name from PHP
          // The display_name is already translated for the current language
          $("#partner_modal").val(promocode.partner_id).trigger("change");

          // Set number of users field (moved before setTimeout for consistency)
          $('input[name="no_of_users"]').val(promocode.no_of_users || '');

          // Set minimum booking amount field
          $('input[name="minimum_order_amount"]').val(promocode.minimum_order_amount || '');

          // Set discount field
          $('input[name="discount"]').val(promocode.discount || '');

          // Set max discount amount field
          $('input[name="max_discount_amount"]').val(promocode.max_discount_amount || '');

          // Set discount type and trigger change to show/hide max discount field
          $("#discount_type").val(promocode.discount_type || 'percentage').trigger("change");

          // Extract only date part (YYYY-MM-DD) from datetime string if present
          // This ensures date pickers show only date, not time
          var startDate = promocode.start_date ? promocode.start_date.split(' ')[0] : '';
          var endDate = promocode.end_date ? promocode.end_date.split(' ')[0] : '';
          $('input[name="start_date"]').val(startDate);
          $('input[name="end_date"]').val(endDate);

          // Set date picker values after a small delay to ensure daterangepicker is initialized
          // This is needed because the modal might not be fully shown when AJAX callback executes
          setTimeout(function () {
            var startDatePicker = $('#start_date');
            var endDatePicker = $('#end_date');

            // Check if daterangepicker is initialized before accessing it
            if (startDatePicker.length && startDatePicker.data('daterangepicker')) {
              var startPicker = startDatePicker.data('daterangepicker');
              if (startDate && typeof startPicker.setStartDate === 'function') {
                startPicker.setStartDate(moment(startDate, 'YYYY-MM-DD'));
              }
            }

            if (endDatePicker.length && endDatePicker.data('daterangepicker')) {
              var endPicker = endDatePicker.data('daterangepicker');
              if (endDate && typeof endPicker.setStartDate === 'function') {
                endPicker.setStartDate(moment(endDate, 'YYYY-MM-DD'));
              }
              // Set minimum date for end date picker based on start date
              if (startDate && typeof endPicker.setMinDate === 'function') {
                endPicker.setMinDate(moment(startDate, 'YYYY-MM-DD'));
              }
            }
          }, 100);

          // Handle image preview - similar to featured sections
          // The backend now returns direct image URLs (or default image if file doesn't exist)
          // So we can use the URL directly without regex extraction
          if (promocode.image && promocode.image.trim() !== '') {
            // Set the image source directly from the URL returned by backend
            // Backend uses get_file_url() which returns default image if file is missing
            $("#preview_promocode_image").attr("src", promocode.image);
          } else {
            // Fallback to default image if no URL is provided
            $("#preview_promocode_image").attr("src", baseUrl + "public/backend/assets/default.png");
          }

          // Load translations into textareas for all languages
          // Get all available languages from the modal (all language options)
          var allLanguages = [];
          var defaultLanguageCode = null;

          $('.language-modal-option').each(function () {
            var langCode = $(this).data('language');
            if (langCode) {
              allLanguages.push(langCode);
              // Check if this is the default language (has "selected" class or contains "(Default)" text)
              if ($(this).hasClass('selected') || $(this).find('.language-modal-text').text().includes('(Default)')) {
                defaultLanguageCode = langCode;
              }
            }
          });

          // If no languages found in modal, try to get from textarea names
          if (allLanguages.length === 0) {
            $('textarea[name^="message["]').each(function () {
              var name = $(this).attr('name');
              var match = name.match(/message\[([^\]]+)\]/);
              if (match && match[1]) {
                allLanguages.push(match[1]);
              }
            });
          }

          // If default language not found, try to find it by looking for the selected option
          if (!defaultLanguageCode) {
            var selectedOption = $('.language-modal-option.selected');
            if (selectedOption.length) {
              defaultLanguageCode = selectedOption.data('language');
            }
          }

          // Populate each language field with translation or fallback to main table message (only for default language)
          allLanguages.forEach(function (language_code) {
            var textarea = $('textarea[name="message[' + language_code + ']"]');
            if (textarea.length) {
              // Check if translation exists for this language
              var translationValue = '';
              if (translations && translations[language_code]) {
                // Use translation from translations table if available
                translationValue = translations[language_code];
              } else if (language_code === defaultLanguageCode) {
                // Only fallback to main table message for default language if no translation exists
                translationValue = promocode.message || '';
              } else {
                // For non-default languages, leave empty if no translation exists
                translationValue = '';
              }
              textarea.val(translationValue);
            }
          });

          // Set status and repeat usage switches after a delay to ensure UI is ready
          setTimeout(function () {
            if (promocode.status == "Active") {
              $(".editInModel").prop("checked", false).trigger("click");
            } else {
              $(".editInModel").prop("checked", true).trigger("click");
            }
            if (promocode.repeat_usage == 1) {
              $("#repeat_usage").prop("checked", false).trigger("click");
              $(".repeat_usage").show();
              $('input[name="no_of_repeat_usage"]').val(promocode.no_of_repeat_usage || '');
            } else {
              $("#repeat_usage").prop("checked", true).trigger("click");
              $(".repeat_usage").hide();
            }
          }, 600);
        } else {
          showToastMessage(response.message, "error");
        }
      },
      error: function () {
        showToastMessage("Error loading promocode data", "error");
      }
    });
  },
};
window.services_events_admin = {
  "click .delete": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        var input_body = {
          [csrfName]: csrfHash,
          id: id,
        };
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/services/delete_service",
          data: input_body,
          dataType: "json",
          success: function (response) {
            csrfName = response["csrfName"];
            csrfHash = response["csrfHash"];
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#service_list").bootstrapTable("refresh");
              }, 2000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                $("#service_list").bootstrapTable("refresh");
              }, 2000);
            }
          },
        });
      }
    });
  },
  "click .edit": function (e, value, row, index) {
    $("#service_id").val(row.id);
    $("#edit_partner").val(row.user_id);
    $("#edit_title").val(row.title);
    $("#edit_category_item").val(row.category_id).trigger("change");
    $("#edit_service_tags").val(row.tags);
    $("#edit_tax_type").val(row.tax_type.trim());
    $("#edit_tax").val(row.tax_id);
    if (row.status_number == "1") {
      $("#edit_status_active").prop("checked", true);
    } else {
      $("#edit_status_deactive").prop("checked", true);
    }
    var regex = /<img.*?src="(.*?)"/;
    if (
      row.image_of_the_service != null &&
      row.image_of_the_service != "nothing found"
    ) {
      var src = regex.exec(row.image_of_the_service)[1];
      source = src;
      $("#edit_service_image").attr("src", source);
    }
    $("#edit_price").val(row.price);
    $("#edit_discounted_price").val(row.discounted_price);
    if (row.on_site_allowed == "Allowed" || row.on_site_allowed == "allowed") {
      $("#edit_on_site").attr("checked", true);
    }
    if (
      row.is_pay_later_allowed == "Allowed" ||
      row.is_pay_later_allowed == "allowed" ||
      row.is_pay_later_allowed == "1"
    ) {
      $("#edit_pay_later").attr("checked", true);
    } else {
      $("#edit_pay_later").attr("checked", false);
    }
    if (row.cancelable == "1" || row.cancelable == "1") {
      $("#edit_is_cancelable").prop("checked", true);
      $("#edit_cancelable_till_value").val(row.cancelable_till);
    } else {
      $("#edit_is_cancelable").prop("checked", false);
      $("#edit_cancelable_till").hide();
      $("#edit_cancelable_till_value").val("empty");
    }
    if (row.cancelable == "1" || row.cancelable == "1") {
      $("#edit_is_cancelable").prop("checked", true);
      $("#edit_cancelable_till_value").val(row.cancelable_till);
    } else {
      $("#edit_is_cancelable").prop("checked", false);
      $("#edit_cancelable_till").hide();
      $("#edit_cancelable_till_value").val("empty");
    }
    $("#edit_members").val(row.number_of_members_required);
    $("#edit_duration").val(row.duration);
    $("#edit_max_qty").val(row.max_quantity_allowed);
    $("#edit_description").text(row.description);
    //
  },
  "click .disapprove_service": function (e, value, row, index) {
    var partner_id = row.user_id;
    var service_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_disapprove_this_service,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        partner_id: partner_id,
        service_id: service_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/services/disapprove_service",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              $("#service_list").bootstrapTable("refresh");
            } else {
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .approve_service": function (e, value, row, index) {
    var partner_id = row.user_id;
    var service_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_approve_this_service,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        partner_id: partner_id,
        service_id: service_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/services/approve_service",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              $("#service_list").bootstrapTable("refresh");
            } else {
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .clone_service": function (e, value, row, index) {
    var partner_id = row.user_id;
    var service_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_clone_this_service,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        partner_id: partner_id,
        service_id: service_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/services/clone_service",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              $("#service_list").bootstrapTable("refresh");
            } else {
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
};
window.subscription_events_admin = {
  "click .delete": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        var input_body = {
          [csrfName]: csrfHash,
          id: id,
        };
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/subscription/delete_subscription",
          data: input_body,
          dataType: "json",
          success: function (response) {
            csrfName = response["csrfName"];
            csrfHash = response["csrfHash"];
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#subscription_list").bootstrapTable("refresh");
              }, 2000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                $("#subscription_list").bootstrapTable("refresh");
              }, 2000);
            }
          },
        });
      }
    });
  },
};
function loadFile(event) {
  var image = document.getElementById("edit_service_image");
  image.src = URL.createObjectURL(event.target.files[0]);
}
window.email_template_actions_events = {
  "click .delete-email-template": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        var input_body = {
          [csrfName]: csrfHash,
          id: id,
        };
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/settings/delete_email_template",
          data: input_body,
          dataType: "json",
          success: function (response) {
            csrfName = response["csrfName"];
            csrfHash = response["csrfHash"];
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#category_list").bootstrapTable("refresh");
              }, 2000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                $("#category_list").bootstrapTable("refresh");
              }, 2000);
            }
          },
        });
      }
    });
  },
};
window.system_user_events = {
  "click .deactivate-user": function (e, value, row, index) {
    var user_id = row.id;
    // return;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_deactivate_this_user,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        user_id: user_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/system_users/deactivate_user",
          data: input_body,
          dataType: "json",
          timeout: 5000,
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            }
          },
        });
      }
    });
  },
  "click .activate-user": function (e, value, row, index) {
    var user_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_activate_this_user,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        user_id: user_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/system_users/activate_user",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            }
          },
        });
      }
    });
  },
  "click .delete-user": function (e, value, row, index) {
    e.preventDefault();
    var user_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_delete_this_user,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        user_id: user_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/system_users/delete_user",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#partner_list").bootstrapTable("refresh");
              }, 2000);
              window.location.reload();
              return;
            } else {
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .edit-user": function (e, value, row, index) {
    $("#id").val(row.id);
    // Populate user information fields
    $("#edit_username").val(row.username || "");
    $("#edit_email").val(row.email || "");
    $("#edit_phone").val(row.phone || "");

    // Always show permissions section (role field removed)
    $("#permissions").show();

    // Clear all permission checkboxes first
    $('[name*="_create_edit"], [name*="_read_edit"], [name*="_update_edit"], [name*="_delete_edit"]').prop("checked", false);

    // Parse and populate permissions
    try {
      var permissions = null;
      if (row.permissions && row.permissions !== "null" && row.permissions !== null && row.permissions !== "") {
        permissions = typeof row.permissions === 'string' ? JSON.parse(row.permissions) : row.permissions;
      }

      if (permissions && typeof permissions === 'object') {
        Object.keys(permissions).forEach((key) => {
          let single_object = permissions[key];
          if (single_object && typeof single_object === 'object') {
            if (key == "create") {
              Object.keys(single_object).forEach((permKey) => {
                // Handle singular/plural mismatch: "order" in create vs "orders" in checkbox ID
                var checkboxKey = permKey;
                if (permKey === "order") {
                  checkboxKey = "orders";
                }
                var checkboxId = checkboxKey + "_create_edit";
                var checkbox = $("#" + checkboxId);
                if (checkbox.length > 0) {
                  checkbox.prop("checked", single_object[permKey] == 1);
                }
              });
            } else if (key == "read") {
              Object.keys(single_object).forEach((permKey) => {
                var checkboxId = permKey + "_read_edit";
                var checkbox = $("#" + checkboxId);
                if (checkbox.length > 0) {
                  checkbox.prop("checked", single_object[permKey] == 1);
                }
              });
            } else if (key == "update") {
              Object.keys(single_object).forEach((permKey) => {
                var checkboxId = permKey + "_update_edit";
                var checkbox = $("#" + checkboxId);
                if (checkbox.length > 0) {
                  checkbox.prop("checked", single_object[permKey] == 1);
                }
              });
            } else if (key == "delete") {
              Object.keys(single_object).forEach((permKey) => {
                var checkboxId = permKey + "_delete_edit";
                var checkbox = $("#" + checkboxId);
                if (checkbox.length > 0) {
                  checkbox.prop("checked", single_object[permKey] == 1);
                }
              });
            }
          }
        });
      }
    } catch (error) {
      console.error("Error parsing permissions:", error, row.permissions);
      // If parsing fails, all checkboxes remain unchecked (already cleared above)
    }
  },
};
function set_attribute_checked(ids) {
  for (let i = 0; i < Object.keys(ids).length; i++) {
    const element = ids[i];
    $(element[0]).attr("checked", true);
  }
}
$("#permissions").show();
$(document).ready(function () {
  $("#role").on("change", function (e) {
    let role = $(this).val();
    if (role == "1") {
      $("#permissions").hide();
    } else {
      $("#permissions").show();
    }
  });
});
$("#edit_role").on("change", function (e) {
  let role = $(this).val();
  if (role == "1") {
    $("#permissions").hide();
  } else {
    $("#permissions").show();
  }
});
window.commission_events = {
  "click .pay-out": function (e, value, row, index) {
    $("#partner_id").val(row.partner_id);
  },
};
window.notification_event = {
  "click .delete-notification": function (e, value, row, index) {
    var users_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/admin/notification/delete_notification",
          {
            [csrfName]: csrfHash,
            user_id: users_id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $("#user_list").bootstrapTable("refresh");
              }, 2000);
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
};
window.partner_events = {
  "click .deactivate_partner": function (e, value, row, index) {
    var id = row.partner_id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_deactivate_this_provider,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        partner_id: id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/partner/deactivate_partner",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#partner_list").bootstrapTable("refresh");
              }, 2000);
              window.location.reload();
              return;
            } else {
              window.location.reload();
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .activate_partner": function (e, value, row, index) {
    var id = row.partner_id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_activate_this_provider,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        partner_id: id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/partner/activate_partner",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#partner_list").bootstrapTable("refresh");
              }, 2000);
              window.location.reload();
              return;
            } else {
              window.location.reload();
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .approve_partner": function (e, value, row, index) {
    var id = row.partner_id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_approve_this_provider,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        partner_id: id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/partner/approve_partner",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              $("#partner_list").bootstrapTable("refresh");
            } else {
              showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .disapprove_partner": function (e, value, row, index) {
    var id = row.partner_id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_disapprove_this_provider,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        partner_id: id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/partner/disapprove_partner",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              $("#partner_list").bootstrapTable("refresh");
              return;
            } else {
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .delete_partner": function (e, value, row, index) {
    var id = row.partner_id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_delete_this_provider,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        partner_id: id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/partner/delete_partner",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                window.location.reload();
              }, 2000);
              return;
            } else {
              window.location.reload();
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
  "click .view_rating": function (e, value, row, index) {
    $("#partner_id").val(row.partner_id);
    var id = row.partner_id;
    $("#rating_table").bootstrapTable("refresh", {
      url: baseUrl + "/admin/partners/view_ratings/" + id,
    });
  },
  "click .edit": function (e, value, row, index) {
    $("#company_name").val(row.company_name);
    if (row.type == "Individual") {
      $("#type").val(0);
    } else {
      $("#type").val(1);
    }
    $("#partner_id").val(row.partner_id);
    $("#about").val(row.about);
    $("#visiting_charges").val(row.visiting_charges);
    $("#advance_booking_days").val(row.advance_booking_days);
    $("#number_of_members").val(row.number_of_members);
    $("#city").val(row.city);
    $("#partner_latitude").val(typeof _fc === 'function' ? _fc(row.latitude) : row.latitude);
    $("#partner_longitude").val(typeof _fc === 'function' ? _fc(row.longitude) : row.longitude);
    $("#address").val(row.address);
    $("#username").val(row.partner_name);
    $("#email").val(row.email);
    $("#phone").val(row.mobile);
    $("#admin_commission").val(row.admin_commission);
    $("#tax_name").val(row.tax_name);
    $("#tax_number").val(row.tax_number);
    $("#account_number").val(row.account_number);
    $("#account_name").val(row.account_name);
    $("#bank_code").val(row.bank_code);
    $("#bank_name").val(row.bank_name);
    $("#swift_code").val(row.swift_code);
    $("#image_preview").attr("src", row.image);
    $("#banner_image_preview").attr("src", row.banner_edit);
    $("#national_id_preview").attr("src", row.national_id);
    $("#passport_preview").attr("src", row.passport);
    $("#address_id_preview").attr("src", row.address_id);
    if (row.is_approved_edit == "1") {
      $("#is_approved_partner").prop("checked", true);
    } else {
      $("#is_disapproved_partner").prop("checked", true);
    }
    if (row.monday_is_open == 1) {
      $("#monday_opening_time").val(row.monday_opening_time);
      $("#monday_closing_time").val(row.monday_closing_time);
      $("#monday").prop("checked", true);
      $("#monday_opening_time").removeAttr("readOnly");
      $("#monday_closing_time").removeAttr("readOnly");
    } else {
      $("#monday_opening_time").val();
      $("#monday_closing_time").val();
      $("#monday").prop("checked", false);
      $("#monday_opening_time").attr("readOnly", "readOnly");
      $("#monday_closing_time").attr("readOnly", "readOnly");
    }
    if (row.tuesday_is_open == 1) {
      $("#tuesday_opening_time").val(row.tuesday_opening_time);
      $("#tuesday_closing_time").val(row.tuesday_closing_time);
      $("#tuesday").prop("checked", true);
      $("#tuesday_opening_time").removeAttr("readOnly");
      $("#tuesday_closing_time").removeAttr("readOnly");
    } else {
      $("#tuesday_opening_time").val();
      $("#tuesday_closing_time").val();
      $("#tuesday").prop("checked", false);
      $("#tuesday_opening_time").attr("readOnly", "readOnly");
      $("#tuesday_closing_time").attr("readOnly", "readOnly");
    }
    if (row.wednesday_is_open == 1) {
      $("#wednesday_opening_time").val(row.wednesday_opening_time);
      $("#wednesday_closing_time").val(row.wednesday_closing_time);
      $("#wednesday").prop("checked", true);
      $("#wednesday_opening_time").removeAttr("readOnly");
      $("#wednesday_closing_time").removeAttr("readOnly");
    } else {
      $("#wednesday_opening_time").val();
      $("#wednesday_closing_time").val();
      $("#wednesday").prop("checked", false);
      $("#wednesday_opening_time").attr("readOnly", "readOnly");
      $("#wednesday_closing_time").attr("readOnly", "readOnly");
    }
    if (row.thursday_is_open == 1) {
      $("#thursday_opening_time").val(row.thursday_opening_time);
      $("#thursday_closing_time").val(row.thursday_closing_time);
      $("#thursday").prop("checked", true);
      $("#thursday_opening_time").removeAttr("readOnly");
      $("#thursday_closing_time").removeAttr("readOnly");
    } else {
      $("#thursday_opening_time").val();
      $("#thursday_closing_time").val();
      $("#thursday").prop("checked", false);
      $("#thursday_opening_time").attr("readOnly", "readOnly");
      $("#thursday_closing_time").attr("readOnly", "readOnly");
    }
    if (row.friday_is_open == 1) {
      $("#friday_opening_time").val(row.friday_opening_time);
      $("#friday_closing_time").val(row.friday_closing_time);
      $("#friday").prop("checked", true);
      $("#friday_opening_time").removeAttr("readOnly");
      $("#friday_closing_time").removeAttr("readOnly");
    } else {
      $("#friday_opening_time").val();
      $("#friday_closing_time").val();
      $("#friday").prop("checked", false);
      $("#friday_opening_time").attr("readOnly", "readOnly");
      $("#friday_closing_time").attr("readOnly", "readOnly");
    }
    if (row.saturday_is_open == 1) {
      $("#saturday_opening_time").val(row.saturday_opening_time);
      $("#saturday_closing_time").val(row.saturday_closing_time);
      $("#saturday").prop("checked", true);
      $("#saturday_opening_time").removeAttr("readOnly");
      $("#saturday_closing_time").removeAttr("readOnly");
    } else {
      $("#saturday_opening_time").val();
      $("#saturday_closing_time").val();
      $("#saturday").prop("checked", false);
      $("#saturday_opening_time").attr("readOnly", "readOnly");
      $("#saturday_closing_time").attr("readOnly", "readOnly");
    }
    if (row.sunday_is_open == 1) {
      $("#sunday_opening_time").val(row.sunday_opening_time);
      $("#sunday_closing_time").val(row.sunday_closing_time);
      $("#sunday").prop("checked", true);
      $("#sunday_opening_time").removeAttr("readOnly");
      $("#sunday_closing_time").removeAttr("readOnly");
    } else {
      $("#sunday_opening_time").val();
      $("#sunday_closing_time").val();
      $("#sunday").prop("checked", false);
      $("#sunday_opening_time").attr("readOnly", "readOnly");
      $("#sunday_closing_time").attr("readOnly", "readOnly");
    }
    $("#number_of_members").attr("readOnly", "readOnly");
    $("#type").change(function () {
      var doc = document.getElementById("type");
      if (doc.options[doc.selectedIndex].value == 0) {
        $("#number_of_members").val("1");
        $("#number_of_members").attr("readOnly", "readOnly");
      } else if (doc.options[doc.selectedIndex].value == 1) {
        $("#number_of_members").val("");
        $("#number_of_members").removeAttr("readOnly");
      }
    });
  },
};
window.rating_event = {
  "click .delete_rating": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_delete_this_rating,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        id: id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/partners/delete_rating",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#rating_table").bootstrapTable("refresh");
              }, 2000);
              window.location.reload();
              return;
            } else {
              window.location.reload();
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
};
window.order_service_events = {
  "click .cancel_order": function (e, value, row, index) {
    var id = row.id;
    var service_id = row.service_id;
    Swal.fire({
      title: are_your_sure,
      text: are_you_sure_you_want_to_cancel_this_service,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      var input_body = {
        [csrfName]: csrfHash,
        id: id,
        service_id: service_id,
      };
      if (result.isConfirmed) {
        $.ajax({
          type: "POST",
          url: baseUrl + "/admin/orders/cancel_order_service",
          data: input_body,
          dataType: "json",
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                $("#ordered_services_list").bootstrapTable("refresh");
              }, 2000);
            } else {
              setTimeout(() => {
                $("#ordered_services_list").bootstrapTable("refresh");
              }, 2000);
              return showToastMessage(response.message, "error");
            }
          },
        });
      }
    });
  },
};
function cancel_service(e) {
  var id = $(e).data("id");
  var service_id = $(e).data("service_id");
  Swal.fire({
    title: are_your_sure,
    text: are_you_sure_you_want_to_cancel_this_service,
    icon: "error",
    showCancelButton: true,
    confirmButtonText: yes_proceed,
    cancelButtonText: cancel,
  }).then((result) => {
    var input_body = {
      [csrfName]: csrfHash,
      id: id,
      service_id: service_id,
    };
    if (result.isConfirmed) {
      $.ajax({
        type: "POST",
        url: baseUrl + "/admin/orders/cancel_order_service",
        data: input_body,
        dataType: "json",
        success: function (response) {
          if (response.error == false) {
            showToastMessage(response.message, "success");
            setTimeout(() => {
              $("#ordered_services_list").bootstrapTable("refresh");
            }, 2000);
            window.location.reload();
            // return;
          } else {
            setTimeout(() => {
              $("#ordered_services_list").bootstrapTable("refresh");
            }, 2000);
            window.location.reload();
            return showToastMessage(response.message, "error");
          }
        },
      });
    }
  });
}
$(document).ready(function () {
  $("#available-slots").hide();
  $(".rescheduled_date").hide();
  $(".work_started_proof").hide();
  $(".work_completed_proof").hide();
  $(".booking_ended_additional_charge").hide();
  $("#status").change(function (e) {
    e.preventDefault();
    var status = $("#status").val();
    if (status === "rescheduled") {
      $("#available-slots").show();
      $(".rescheduled_date").show();
      $(".work_started_proof").hide();
      $(".work_completed_proof").hide();
      $(".booking_ended_additional_charge").hide();
    } else {
      $("#available-slots").hide();
      $(".rescheduled_date").hide();
      $(".work_started_proof").hide();
      $(".work_completed_proof").hide();
      $(".booking_ended_additional_charge").hide();
    }
    if (status == "started") {
      $(".work_started_proof").show();
    } else {
      $(".work_started_proof").hide();
    }
    // if (status == "completed") {
    // } else {
    //   $(".work_completed_proof").hide();
    // }
    if (status == "booking_ended") {
      $(".booking_ended_additional_charge").show();
      $(".work_completed_proof").show();
    } else {
      $(".booking_ended_additional_charge").hide();
      $(".work_completed_proof").hide();
    }
  });
  $("#rescheduled_date").change(function (e) {
    $("#available-slots").empty();
    var weekday = new Array(7);
    e.preventDefault();
    var date = $("#rescheduled_date").val();
    var d = new Date(date);
    var id = $("#order_id").val();
    var input_body = {
      [csrfName]: csrfHash,
      id: id,
      date: date,
    };
    $.ajax({
      type: "POST",
      url: baseUrl + "/admin/orders/get_slots",
      data: input_body,
      dataType: "JSON",
      success: function (response) {
        // Helper to sanitize any text coming from the server before rendering.
        // This is an extra safety net on top of using jQuery's .text() and .val().
        function sanitizeSlotText(value) {
          if (value === null || value === undefined) {
            return "";
          }
          // Convert to string and strip angle brackets so tags cannot be formed.
          // This keeps the displayed content but removes any potential HTML.
          return String(value).replace(/[<>]/g, "");
        }

        if (response.error == false) {
          var slots = response.available_slots;
          var slot_selector = "";
          if (slots == "") {
            slot_selector += `   <div class="col-md-12 form-group">
                                       <div class="selectgroup">
                                           <label class="selectgroup-item">
                                           <span class="text-danger">There is no slot available on this date!</span>
                                           </label>                                    
                                       </div>
                                   </div>
                                    `;
          } else {
            // Build each slot element using DOM APIs to avoid injecting raw HTML.
            // Sanitize slot text before using it, then treat it purely as text.
            slots.forEach((element) => {
              var safeElement = sanitizeSlotText(element);

              // Create outer column div
              var $col = $("<div>").addClass("col-md-2 form-group");

              // Create selectgroup structure
              var $selectGroup = $("<div>").addClass("selectgroup");
              var $label = $("<label>").addClass("selectgroup-item");

              // Create radio input; jQuery will escape the value attribute
              var $input = $("<input>")
                .attr("type", "radio")
                .attr("name", "reschedule")
                .addClass("selectgroup-input")
                .val(safeElement);

              // Create span container
              var $span = $("<span>").addClass("selectgroup-button selectgroup-button-icon");
              var $icon = $("<i>").addClass("fas fa-sun");

              // Create text container and set text safely (no HTML parsing)
              var $textDiv = $("<div>").addClass("text-dark").text(safeElement);

              // Assemble structure
              $span.append($icon).append(" \u00a0 "); // non-breaking space
              $span.append($textDiv);
              $label.append($input).append($span);
              $selectGroup.append($label);
              $col.append($selectGroup);

              // Append built DOM node to the container
              $("#available-slots").append($col);
            });
          }
          // For the "no slots" case, we still use the prebuilt HTML above.
          // For the slots list, elements are appended individually via jQuery DOM APIs.
          $("#available-slots").append(slot_selector);
        } else {
          var slot_selector = "";
          if (response.error == true) {
            // Build the error block safely using text nodes instead of string concatenation.
            var $col = $("<div>").addClass("col-md-12 form-group");
            var $selectGroup = $("<div>").addClass("selectgroup");
            var $label = $("<label>").addClass("selectgroup-item");

            // Use .text() to ensure any HTML in the message is escaped.
            var $span = $("<span>")
              .addClass("text-danger")
              .text(response.message == null ? "" : response.message);

            $label.append($span);
            $selectGroup.append($label);
            $col.append($selectGroup);

            slot_selector += $col.prop("outerHTML");
          }
          $("#available-slots").append(slot_selector);
          setTimeout(() => {
            $("#ordered_services_list").bootstrapTable("refresh");
          }, 2000);
        }
      },
    });
  });
  $("#change_status").on("click", function (e) {
    e.preventDefault();
    var status = $("#status").val();
    var order_id = $("#order_id").val();
    var date = $("#rescheduled_date").val();
    var is_otp_enable = $("#is_otp_enable").val();
    var payment_method = $("#payment_method").val();

    var selected_time = "";
    var formdata = new FormData($("#myForm")[0]);
    if ($(".selectgroup-input").length > 1) {
      selected_time = $('input[name="reschedule"]:checked').val();
    }
    if (is_otp_enable == 1) {
      if (status == "completed") {
        Swal.fire({
          title: are_your_sure,
          text: you_wont_be_able_to_revert_this,
          icon: "error",
          input: "number",
          inputPlaceholder: enter_otp_here,
          inputAttributes: {
            autocapitalize: "off",
            required: "true",

          },
          showCancelButton: true,
          cancelButtonText: cancel,
          confirmButtonText: yes_proceed,
        }).then((result) => {
          if (result.value) {
            formdata.append("otp", result.value);
            $.ajaxSetup({
              headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
              },
            });
            $.ajax({
              url: baseUrl + "/admin/orders/change_order_status",
              data: formdata,
              processData: false,
              contentType: false,
              type: "post",
              dataType: "json",
              beforeSend: function () {
                $("#change_status").attr("disabled", true);
                $("#change_status").removeClass("btn-primary");
                $("#change_status").addClass("btn-secondary");
                $("#change_status").html(
                  '<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
                );
              },
              success: function (response) {
                if (response.error == false) {
                  showToastMessage(response.message, "success");
                  setTimeout(() => {
                    window.location.reload();
                  }, 3000);
                } else {
                  showToastMessage(response.message, "error");
                  setTimeout(() => {
                    window.location.reload();
                  }, 3000);
                }
                return;
              },
              error: function (response) {
                showToastMessage(response.message, "error");
                setTimeout(() => {
                  window.location.reload();
                }, 3000);
              },
            });
          }
        });
      } else {
        $.ajaxSetup({
          headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
          },
        });
        $.ajax({
          url: baseUrl + "/admin/orders/change_order_status",
          data: formdata,
          type: "post",
          dataType: "json",
          processData: false,
          contentType: false,
          beforeSend: function () {
            $("#change_status").attr("disabled", true);
            $("#change_status").removeClass("btn-primary");
            $("#change_status").addClass("btn-secondary");
            $("#change_status").html(
              '<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
            );
          },
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            }
            return;
          },
          error: function (xhr) {
            showToastMessage(response.message, "error");
            setTimeout(() => {
              window.location.reload();
            }, 3000);
          },
        });
      }
    } else {
      $.ajaxSetup({
        headers: {
          "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
      });

      if (status == "completed") {
        if (payment_method == "cod") {
          Swal.fire({
            title: are_your_sure,
            text: make_sure_you_have_collected_cash_amount_before_completing_the_booking,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: yes_proceed,
            cancelButtonText: cancel,
          }).then((result) => {
            if (result.isConfirmed) {
              $.ajax({
                url: baseUrl + "/admin/orders/change_order_status",
                data: formdata,
                processData: false,
                contentType: false,
                type: "post",
                dataType: "json",
                beforeSend: function () {
                  $("#change_status").attr("disabled", true);
                  $("#change_status").removeClass("btn-primary");
                  $("#change_status").addClass("btn-secondary");
                  $("#change_status").html(
                    '<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
                  );
                },
                success: function (response) {
                  if (response.error == false) {
                    showToastMessage(response.message, "success");
                    setTimeout(() => {
                      window.location.reload();
                    }, 3000);
                  } else {
                    showToastMessage(response.message, "error");
                    setTimeout(() => {
                      window.location.reload();
                    }, 3000);
                  }
                  return;
                },
                error: function (response) {
                  showToastMessage(response.message, "error");
                  setTimeout(() => {
                    window.location.reload();
                  }, 3000);
                },
              });
            }
          });
        } else {
          $.ajax({
            url: baseUrl + "/admin/orders/change_order_status",
            data: formdata,
            processData: false,
            contentType: false,
            type: "post",
            dataType: "json",
            beforeSend: function () {
              $("#change_status").attr("disabled", true);
              $("#change_status").removeClass("btn-primary");
              $("#change_status").addClass("btn-secondary");
              $("#change_status").html(
                '<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
              );
            },
            success: function (response) {
              if (response.error == false) {
                showToastMessage(response.message, "success");
                setTimeout(() => {
                  window.location.reload();
                }, 3000);
              } else {
                showToastMessage(response.message, "error");
                setTimeout(() => {
                  window.location.reload();
                }, 3000);
              }
              return;
            },
            error: function (response) {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            },
          });
        }
      } else {
        $.ajax({
          url: baseUrl + "/admin/orders/change_order_status",
          data: formdata,
          processData: false,
          contentType: false,
          type: "post",
          dataType: "json",
          beforeSend: function () {
            $("#change_status").attr("disabled", true);
            $("#change_status").removeClass("btn-primary");
            $("#change_status").addClass("btn-secondary");
            $("#change_status").html(
              '<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
            );
          },
          success: function (response) {
            if (response.error == false) {
              showToastMessage(response.message, "success");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                window.location.reload();
              }, 3000);
            }
            return;
          },
          error: function (response) {
            showToastMessage(response.message, "error");
            setTimeout(() => {
              window.location.reload();
            }, 3000);
          },
        });
      }
    }
  });
});
window.cash_collection_events = {
  "click .edit_cash_collection": function (e, value, row, index) {
    $("#partner_id").val(row.partner_id);
    $("#amount").val(row.payable_commision);
  },
};
window.email_events = {
  "click .delete-email": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/admin/delete_email",
          {
            [csrfName]: csrfHash,
            id: id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $("#email_list").bootstrapTable("refresh");
              }, 2000);
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
};
window.sms_gateway_events = {
  "click .edit": function (e, value, row, index) {
    $("#partner_id").val(row.partner_id);
    $("#amount").val(row.payable_commision);
  },
};


window.rejection_reasons_events = {
  "click .remove_reason": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/admin/remove-rejection-reasons",
          {
            [csrfName]: csrfHash,
            id: id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $("#user_list").bootstrapTable("refresh");
              }, 2000);
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
  // Edit reason: fetch per-language data from API, prefill modal, then open it
  "click .edit_reason": function (e, value, row, index) {
    var id = row.id;

    // Fetch full reason data including all language translations
    $.ajax({
      url: baseUrl + "/admin/reason_for_report_and_block_chat/get_reason_data",
      type: "POST",
      data: {
        id: id,
        [csrfName]: csrfHash,
      },
      dataType: "json",
      success: function (response) {
        // Update CSRF tokens for next request
        csrfName = response.csrfName;
        csrfHash = response.csrfHash;

        if (response.error) {
          showToastMessage(response.message, "error");
          return;
        }

        var data = response.data;

        // Set the hidden reason ID
        $("#id").val(data.id);

        // Set the needs_additional_info checkbox
        $("#edit_needs_additional_info").prop(
          "checked",
          data.needs_additional_info == 1
        );

        // Clear all language inputs first so stale values from a previous edit don't remain
        $("[id^='edit_reason']").val("");

        // Prefill each language tab from the translations object.
        // Backend returns the default language value from the base table when no translation exists.
        // Other languages stay empty if there is no translation.
        if (data.translations) {
          Object.keys(data.translations).forEach(function (langCode) {
            var $input = $("#edit_reason" + langCode);
            if ($input.length) {
              $input.val(data.translations[langCode] || "");
            }
          });
        }

        // Open the modal only after data is loaded and fields are prefilled
        $("#update_modal").modal("show");
      },
      error: function () {
        showToastMessage("Failed to fetch reason data", "error");
      },
    });
  },
};

window.language_events = {
  'click .edit-language': function (e, value, row, index) {
    $('#id').val(row.id);
    $("#edit_name").val(row.language);
    $("#edit_code").val(row.code);
    if (row.is_rtl_og == "1") {
      $("#is_rtl_edit").attr("checked", true);
    } else {
      $("#is_rtl_edit").attr("checked", false);
    }

    // Show current image preview for the language being edited
    // This ensures the correct flag/image is displayed for each language
    if (row.image && row.image !== 'null') {
      $('#current_image').attr('src', row.image);
      $('#current_image_preview').show();
    } else {
      $('#current_image_preview').hide();
    }
  },
  'click .delete-language': function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: 'error',
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/admin/language/remove", {
          [csrfName]: csrfHash,
          id: id,
        },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;

            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $('#language_list').bootstrapTable('refresh')
              }, 2000)

              // Refresh language dropdown if function exists
              if (typeof refreshLanguageDropdown === 'function') {
                setTimeout(() => {
                  refreshLanguageDropdown();
                }, 2500);
              }
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        )
      }
    });
  }
};

window.Category_events = {
  "click .delete-Category": function (e, value, row, index) {
    var users_id = row.id;
    Swal.fire({
      title: are_your_sure,
      text:
        you_wont_be_able_to_revert_this +
        " " +
        subcategories_and_services_will_be_deactivated,
      icon: "error",
      showCancelButton: true,
      cancelButtonText: cancel,
      confirmButtonText: yes_proceed,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/admin/category/remove_category",
          {
            [csrfName]: csrfHash,
            user_id: users_id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $("#category_list").bootstrapTable("refresh");
                $("#edit_category_ids")
                  .children("option[value^=" + users_id + "]")
                  .remove();
              }, 2000);
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
  "click .edite-Category": function (e, value, row, index) {
    // Get category ID from row data
    var categoryId = row.id;

    if (!categoryId) {
      console.error('Category ID not found');
      // alert('Error: Category ID not found. Please try again.');
      return;
    }

    // Set hidden ID field
    $("#id").val(categoryId);

    // Wait for modal to be fully shown, then fetch and populate data
    $('#update_modal').one('shown.bs.modal', function () {
      // Use baseUrl and csrfName/csrfHash if available, otherwise fallback to base_url and csrf_token_name/csrf_token_value
      var baseUrlToUse = typeof baseUrl !== 'undefined' ? baseUrl : (typeof base_url !== 'undefined' ? base_url : '');
      var csrfNameToUse = typeof csrfName !== 'undefined' ? csrfName : (typeof csrf_token_name !== 'undefined' ? csrf_token_name : '');
      var csrfHashToUse = typeof csrfHash !== 'undefined' ? csrfHash : (typeof csrf_token_value !== 'undefined' ? csrf_token_value : '');

      // Fetch category data with translations and SEO settings
      $.ajax({
        url: baseUrlToUse + 'admin/categories/get_category_data',
        type: 'POST',
        data: {
          id: categoryId,
          [csrfNameToUse]: csrfHashToUse
        },
        dataType: 'json',
        success: function (response) {
          // Update CSRF tokens if provided
          if (response.csrfName && response.csrfHash) {
            if (typeof csrfName !== 'undefined') csrfName = response.csrfName;
            if (typeof csrfHash !== 'undefined') csrfHash = response.csrfHash;
            if (typeof csrf_token_name !== 'undefined') csrf_token_name = response.csrfName;
            if (typeof csrf_token_value !== 'undefined') csrf_token_value = response.csrfHash;
          }

          if (response.error === false && response.data) {
            var categoryData = response.data;

            // Populate basic fields
            $("#edit_category_slug").val(categoryData.slug || '');
            $("#edit_dark_theme_color").val(categoryData.dark_color || '#2A2C3E');
            $("#edit_light_theme_color").val(categoryData.light_color || '#FFFFFF');

            // Restore all parent category options
            var parentSelect = $("#edit_category_ids");
            parentSelect.find("option").show();

            // Set parent category
            if (categoryData.parent_id && categoryData.parent_id > 0) {
              $("#edit_make_parent").val("1");
              $("#edit_parent").show();
              $("#edit_category_ids").val(categoryData.parent_id);
            } else {
              $("#edit_make_parent").val("0");
              $("#edit_parent").hide();
            }

            // Hide current category from parent dropdown to prevent self-selection
            var currentCategoryOption = parentSelect.find("option[value='" + categoryData.id + "']");
            if (currentCategoryOption.length > 0) {
              currentCategoryOption.hide();
            }

            // Populate all translation fields (multilanguage names)
            if (categoryData.translations) {
              Object.keys(categoryData.translations).forEach(function (languageCode) {
                var translation = categoryData.translations[languageCode];
                var inputId = "edit_name_modal" + languageCode;
                var inputElement = $("#" + inputId);

                if (inputElement.length > 0) {
                  // Handle both object format {name: "value"} and direct string format
                  var nameValue = '';
                  if (typeof translation === 'string') {
                    nameValue = translation;
                  } else if (translation && typeof translation === 'object' && translation.name) {
                    nameValue = translation.name;
                  }
                  inputElement.val(nameValue || '');
                }
              });
            }

            // Populate multilanguage SEO settings
            if (categoryData.seo_translations) {
              Object.keys(categoryData.seo_translations).forEach(function (languageCode) {
                var seoTranslation = categoryData.seo_translations[languageCode];

                // Populate meta title
                var titleInputId = "#edit_meta_title" + languageCode;
                if ($(titleInputId).length > 0) {
                  $(titleInputId).val(seoTranslation.seo_title || '');
                }

                // Populate meta description
                var descriptionInputId = "#edit_meta_description" + languageCode;
                if ($(descriptionInputId).length > 0) {
                  $(descriptionInputId).val(seoTranslation.seo_description || '');
                }

                // Populate schema markup
                var schemaInputId = "#edit_schema_markup" + languageCode;
                if ($(schemaInputId).length > 0) {
                  $(schemaInputId).val(seoTranslation.seo_schema_markup || '');
                }

                // Handle meta keywords (Tagify format)
                var keywordsInputId = "#edit_meta_keywords" + languageCode;
                var tagifyInput = document.querySelector(keywordsInputId);

                if (tagifyInput) {
                  // Prepare keywords array
                  var keywordsArray = [];
                  if (seoTranslation.seo_keywords) {
                    if (typeof seoTranslation.seo_keywords === 'string') {
                      keywordsArray = seoTranslation.seo_keywords.split(',').map(function (keyword) {
                        return keyword.trim();
                      }).filter(function (keyword) {
                        return keyword.length > 0;
                      });
                    } else if (Array.isArray(seoTranslation.seo_keywords)) {
                      keywordsArray = seoTranslation.seo_keywords;
                    }
                  }

                  // Populate Tagify tags
                  if (tagifyInput.tagify) {
                    try {
                      tagifyInput.tagify.removeAllTags();
                      if (keywordsArray.length > 0) {
                        tagifyInput.tagify.addTags(keywordsArray);
                      }
                    } catch (error) {
                      console.warn('Error updating Tagify tags for ' + keywordsInputId, error);
                    }
                  } else if (typeof Tagify !== 'undefined' && keywordsArray.length > 0) {
                    // Initialize Tagify if not already initialized
                    try {
                      if (!tagifyInput.tagify) {
                        new Tagify(tagifyInput);
                      }
                      setTimeout(function () {
                        var retryInput = document.querySelector(keywordsInputId);
                        if (retryInput && retryInput.tagify) {
                          retryInput.tagify.removeAllTags();
                          retryInput.tagify.addTags(keywordsArray);
                        } else if (retryInput) {
                          retryInput.value = keywordsArray.join(', ');
                        }
                      }, 50);
                    } catch (error) {
                      console.warn('Failed to initialize Tagify for ' + keywordsInputId, error);
                      if (tagifyInput) {
                        tagifyInput.value = keywordsArray.join(', ');
                      }
                    }
                  }
                }
              });
            }

            // Handle meta image (shared across all languages)
            var metaImage = null;
            if (categoryData.seo_settings && categoryData.seo_settings.image) {
              metaImage = categoryData.seo_settings.image;
            }

            if (metaImage) {
              var imageUrl = baseUrlToUse + 'public/uploads/seo_settings/category_seo_settings/' + metaImage;
              $("#edit_meta_image_preview").attr('src', imageUrl);
              $("#edit_categoryMetaImage").show();
            } else {
              $("#edit_categoryMetaImage").hide();
            }

            // Show category image
            if (categoryData.image) {
              var imageUrl = baseUrlToUse + 'public/uploads/categories/' + categoryData.image;
              $("#category_image").attr('src', imageUrl);
            }
          } else {
            console.error('Failed to fetch category data:', response.message);
            // alert('Failed to load category data: ' + (response.message || 'Unknown error'));
          }
        },
        error: function (xhr, status, error) {
          console.error('Error fetching category data:', error);
          // alert('An error occurred while loading category data. Please check the console for details.');
        }
      });
    });
  },
};