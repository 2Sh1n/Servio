"use strict";
$(document).ready(function () {
  $("#available-slots").hide();
  $(".rescheduled_date").hide();
  $(".work_started_proof").hide();
  $(".work_completed_proof").hide();
  $(".booking_ended_additional_charge").hide();
  const $rescheduleInput = $("#rescheduled_date");
  const rescheduleMinDate = $rescheduleInput.data("min-date") || "";
  const rescheduleMaxDate = $rescheduleInput.data("max-date") || "";
  const rawAdvanceDays = $rescheduleInput.data("advance-days");
  const parsedAdvanceDays =
    rawAdvanceDays === "" || typeof rawAdvanceDays === "undefined"
      ? null
      : parseInt(rawAdvanceDays, 10);
  const allowedAdvanceDays = Number.isNaN(parsedAdvanceDays)
    ? null
    : parsedAdvanceDays;
  const minDateError =
    $rescheduleInput.data("min-error") || "Please select an upcoming date.";
  const maxDateError =
    $rescheduleInput.data("max-error") ||
    "You cannot choose a date beyond allowed advance booking days.";
  const noAdvanceError =
    $rescheduleInput.data("no-advance-error") ||
    "Advanced booking for this partner is not available.";

  const resetRescheduleUI = () => {
    // Clear slots whenever the selected date becomes invalid so we never show stale options.
    $("#available-slots").empty();
  };

  const isBeforeMin = (value) =>
    rescheduleMinDate !== "" && value < rescheduleMinDate;
  const isBeyondMax = (value) =>
    rescheduleMaxDate !== "" && value > rescheduleMaxDate;

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
      $(".booking_ended_additional_charge").hide();
    } else {
      $(".work_started_proof").hide();
      $(".booking_ended_additional_charge").hide();
    }
    // if (status == "completed") {
    //   $(".work_completed_proof").show();
    //   $(".booking_ended_additional_charge").hide();

    // } else {
    //   $(".work_completed_proof").hide();
    //   $(".booking_ended_additional_charge").hide();

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
    e.preventDefault();
    resetRescheduleUI();
    var date = $("#rescheduled_date").val();
    if (!date) {
      return;
    }
    if (isBeforeMin(date)) {
      showToastMessage(minDateError, "error");
      $(this).val("");
      return;
    }
    if (
      allowedAdvanceDays === 0 &&
      rescheduleMinDate !== "" &&
      date > rescheduleMinDate
    ) {
      showToastMessage(noAdvanceError, "error");
      $(this).val("");
      return;
    }
    if (isBeyondMax(date)) {
      showToastMessage(maxDateError, "error");
      $(this).val("");
      return;
    }
    var id = $("#order_id").val();
    var input_body = {
      [csrfName]: csrfHash,
      id: id,
      date: date,
    };
    $.ajax({
      type: "POST",
      url: baseUrl + "/partner/orders/get_slots",
      data: input_body,
      dataType: "JSON",
      success: function (response) {
        if (response.error == false) {
          var slots = response.available_slots;

          // IMPORTANT SECURITY NOTE:
          // We now create DOM elements manually instead of building an HTML string.
          // This way, the slot value (which may come from the server / remote source)
          // is only ever inserted as plain text or as an input value.
          // This prevents DOM-based XSS, because we do not inject unsanitized HTML.
          var $slotsContainer = $("#available-slots");

          slots.forEach((element) => {
            // Create the outer column container
            var $col = $('<div class="col-md-2 form-group"></div>');

            // Inner selectgroup wrapper
            var $selectgroup = $('<div class="selectgroup"></div>').appendTo($col);

            // Label for radio + content
            var $label = $('<label class="selectgroup-item"></label>').appendTo(
              $selectgroup
            );

            // Radio input - value is set directly, not via innerHTML
            $('<input type="radio" name="reschedule" class="selectgroup-input">')
              .val(element)
              .appendTo($label);

            // Visual span with icon and text
            var $span = $(
              '<span class="selectgroup-button selectgroup-button-icon"></span>'
            ).appendTo($label);

            // Static icon HTML (safe, as it is not user-controlled)
            $span.append('<i class="fas fa-sun "></i>&nbsp;');

            // Slot text is inserted as plain text, not HTML, to avoid XSS
            $("<div class=\"text-dark\"></div>").text(element).appendTo($span);

            // Append the fully built, safe node into the container
            $slotsContainer.append($col);
          });
        } else {
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
              url: baseUrl + "/partner/orders/update_order_status",
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
                // Parse response if it's a string
                if (typeof response === 'string') {
                  try {
                    response = JSON.parse(response);
                  } catch (e) {
                    console.error('Failed to parse response:', e);
                  }
                }
                
                if (response.error == false) {
                  showToastMessage(response.message, "success");
                  
                  // Track Microsoft Clarity booking events
                  if (response.data && response.data.clarity_event) {
                    var eventType = response.data.clarity_event;
                    var bookingId = response.data.booking_id;
                    var status = response.data.status;
                    var customerId = response.data.customer_id;
                    
                    if (eventType === 'booking_accepted' && typeof trackBookingAccepted === 'function') {
                      trackBookingAccepted(bookingId, status, customerId);
                    } else if (eventType === 'booking_rejected' && typeof trackBookingRejected === 'function') {
                      trackBookingRejected(bookingId, status, customerId);
                    } else if (eventType === 'booking_cancelled' && typeof trackBookingCancelled === 'function') {
                      trackBookingCancelled(bookingId, status, customerId);
                    } else if (eventType === 'booking_completed' && typeof trackBookingCompleted === 'function') {
                      trackBookingCompleted(bookingId, status, customerId);
                    } else if (eventType === 'booking_status_updated' && typeof trackBookingStatusUpdated === 'function') {
                      trackBookingStatusUpdated(bookingId, status, customerId);
                    }
                  }
                  
                  setTimeout(() => {
                    window.location.reload(true);
                  }, 2000);
                } else {
                  showToastMessage(response.message, "error");
                  setTimeout(() => {
                    window.location.reload(true);
                  }, 2000);
                }
                return;
              },
              error: function (response) {},
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
          url: baseUrl + "/partner/orders/update_order_status",
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
            // Parse response if it's a string
            if (typeof response === 'string') {
              try {
                response = JSON.parse(response);
              } catch (e) {
                console.error('Failed to parse response:', e);
              }
            }
            
            if (response.error == false) {
                showToastMessage(response.message, "success");
              
              // Track Microsoft Clarity booking events
              if (response.data && response.data.clarity_event) {
                var eventType = response.data.clarity_event;
                var bookingId = response.data.booking_id;
                var status = response.data.status;
                var customerId = response.data.customer_id;
                
                if (eventType === 'booking_accepted' && typeof trackBookingAccepted === 'function') {
                  trackBookingAccepted(bookingId, status, customerId);
                } else if (eventType === 'booking_rejected' && typeof trackBookingRejected === 'function') {
                  trackBookingRejected(bookingId, status, customerId);
                } else if (eventType === 'booking_cancelled' && typeof trackBookingCancelled === 'function') {
                  trackBookingCancelled(bookingId, status, customerId);
                } else if (eventType === 'booking_completed' && typeof trackBookingCompleted === 'function') {
                  trackBookingCompleted(bookingId, status, customerId);
                } else if (eventType === 'booking_status_updated' && typeof trackBookingStatusUpdated === 'function') {
                  trackBookingStatusUpdated(bookingId, status, customerId);
                }
              }
              
              setTimeout(() => {
                window.location.reload(true);
              }, 2000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                window.location.reload(true);
              }, 2000);
            }
            return;
          },
          error: function (xhr) {
            showToastMessage(response.message, "error");
            setTimeout(() => {
              window.location.reload(true);
            }, 2000);
          },
        });
      }
    } else {
      $.ajaxSetup({
        headers: {
          "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
      });
      $.ajax({
        url: baseUrl + "/partner/orders/update_order_status",
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
              window.location.reload(true);
            }, 2000);
          } else {
            showToastMessage(response.message, "error");
            setTimeout(() => {
              window.location.reload(true);
            }, 2000);
          }
          return;
        },
        error: function (response) {
          showToastMessage(response.message, "error");
          setTimeout(() => {
            window.location.reload(true);
          }, 2000);
        },
      });
    }
  });
});
window.order_service_event = {
  "click .cancel_service": function (e, value, row, index) {},
};
