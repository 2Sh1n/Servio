"use client";
import React, { useState, useEffect } from "react";
import Layout from "../Layout/Layout";
import BreadCrumb from "../ReUseableComponents/BreadCrumb";
import { IoStorefrontOutline, IoTimeOutline } from "react-icons/io5";
import { BsCalendar3Week, BsHouse } from "react-icons/bs";
import { FaCirclePlus, FaLocationDot } from "react-icons/fa6";
import { MdClose, MdModeEdit } from "react-icons/md";
import { BiSolidEdit } from "react-icons/bi";
import stripe from "../../assets/stripe.png";
import paypal from "../../assets/paypal.png";
import paystack from "../../assets/paystack.png";
import flutterwave from "../../assets/flutterwave.png";
import razorpay from "../../assets/razorpay.png";
import xendit from "../../assets/xendit.png";
import cod from "../../assets/cod.png";
import card from "../../assets/card.png";
import SelectDateAndTimeDrawer from "../ReUseableComponents/Drawers/SelectDateAndTimeDrawer";
import { loadStripeApiKey, showPrice } from "@/utils/Helper";
import AddressDrawer from "../ReUseableComponents/Drawers/AddressDrawer";
import CustomImageTag from "../ReUseableComponents/CustomImageTag";
import { useRouter } from "next/router";
import { useDispatch, useSelector } from "react-redux";
import {
  clearCart,
  clearChekoutData,
  setDilveryDetails,
  setPromocodeDiscount,
  selectCustomJobData,
  selectCartProvider,
  setAppliedCoupon,
  selectAppliedCoupon,
  selectTaxValue,
} from "@/redux/reducers/cartSlice";
import { FaChevronRight, FaInfoCircle, FaPercentage } from "react-icons/fa";
import {
  addTransactionsApi,
  createRazorOrderApi,
  createStripePaymentIntentApi,
  getAddressApi,
  getPromoCodeApi,
  placeOrderApi,
  providerAvailableApi,
  validatePromocodeApi,
} from "@/api/apiRoutes";
import OfferModal from "../ReUseableComponents/Offer/OfferModal";
import { toast } from "sonner";
import dayjs from "dayjs";
import { useTranslation } from "../Layout/TranslationContext";
import { loadStripe } from "@stripe/stripe-js";
import StripePayment from "./PaymentGateways/StripePayment";
import PaystackPop from "@paystack/inline-js";
import withAuth from "../Layout/withAuth";
import { selectReorderMode, clearReorder } from "@/redux/reducers/reorderSlice";

import { setReorderMode } from "@/redux/reducers/reorderSlice";
import MiniLoader from "../ReUseableComponents/MiniLoader";
import { logClarityEvent } from "@/utils/clarityEvents";
import { BOOKING_EVENTS, PAYMENT_EVENTS } from "@/constants/clarityEventNames";

const stripeLoadKey = loadStripeApiKey();
const stripePromise = loadStripe(stripeLoadKey);

const Checkout = () => {
  const router = useRouter();
  const { isRepayment } = router.query; // Get repayment flag from URL query
  const dispatch = useDispatch();
  const t = useTranslation();

  // Get reorder state
  const isReorderMode = useSelector(selectReorderMode);

  const reorderState = useSelector((state) => state.reorder);

  // Use reorder data if in reorder mode, otherwise use cart data
  const currentCartProviderData = useSelector((state) =>
    isReorderMode ? reorderState.provider : selectCartProvider(state)
  );

  const dilveryDetails = useSelector((state) => state.cart.dilveryDetails);
  const settingsData = useSelector((state) => state?.settingsData);
  const userDetails = useSelector((state) => state?.userData?.data);
  const taxConfig = useSelector((state) => state?.settingsData?.settings?.system_tax_settings);
  const showTax = taxConfig?.show_on_checkout === 1 || taxConfig?.show_on_checkout === "1";


  const userEmail = userDetails?.email;

  const promocodeDiscount = useSelector(
    (state) => state.cart.promocode_discount
  );

  const taxValue = useSelector(selectTaxValue);
  const availableOnHome = currentCartProviderData?.at_doorstep === "1";

  const availableOnStore = currentCartProviderData?.at_store === "1";

  const [serviceType, setServiceType] = useState(
    isReorderMode
      ? reorderState.provider?.dilveryAddressType
      : dilveryDetails?.dilveryAddressType
        ? dilveryDetails?.dilveryAddressType
        : ""
  );

  const [paymentOption, setPaymentOption] = useState(
    isReorderMode
      ? reorderState.provider?.dilevryPymentMethod
      : dilveryDetails?.dilevryPymentMethod
        ? dilveryDetails?.dilevryPymentMethod
        : ""
  );

  const [note, setNote] = useState(
    dilveryDetails?.dilveryNote ? dilveryDetails?.dilveryNote : ""
  );

  const [activeNotes, setActiveNotes] = useState(false);
  const [scheduleDrawerOpen, setScheduleDrawerOpen] = useState(false);
  const [addressDrawerOpen, setAddressDrawerOpen] = useState(false);

  const [defaultAddress, setDefaultAddress] = useState(null);
  const [offers, setOffers] = useState([]);
  const [offersModalOpen, setOffersModalOpen] = useState(false);
  const [addresses, setAddresses] = useState([]);
  const [isProcessingCheckout, setIsProcessingCheckout] = useState(false);

  // payment variables
  const paymentSettings = settingsData?.settings?.payment_gateways_settings;

  const isPayLaterAllowed = Number(currentCartProviderData?.is_pay_later_allowed) === 1;
  const isPayOnlineAllowed = Number(currentCartProviderData?.is_online_payment_allowed) === 1;

  // Immediate mount check to catch manual back from redirect-based gateways
  useEffect(() => {
    const PENDING_KEY = "Servio_pending_payment";
    let pending = null;
    try {
      const raw = localStorage.getItem(PENDING_KEY);
      pending = raw ? JSON.parse(raw) : null;
    } catch (_) {
      pending = null;
    }

    if (pending?.orderId) {
      try {
        localStorage.removeItem(PENDING_KEY);
      } catch (_) { }
      (async () => {
        try {
          await addTransactionsApi({
            order_id: pending.orderId,
            status: "failed",
            is_reorder: isReorderMode ? "1" : "",
          });
        } catch (_) {
          // ignore network error
        } finally {
          setIsProcessingCheckout(false);
          router.push(
            `/payment-status?order_id=${pending.orderId}&status=failed`
          );
        }
      })();
    }
  }, [isReorderMode, router]);

  // Handle browser back/forward restoration from BFCache after redirect-based payments (e.g., Xendit)
  useEffect(() => {
    // Local helpers to manage pending redirect-based payments
    const PENDING_KEY = "Servio_pending_payment";
    function getPendingPayment() {
      try {
        const raw = localStorage.getItem(PENDING_KEY);
        return raw ? JSON.parse(raw) : null;
      } catch (_) {
        return null;
      }
    }
    function clearPendingPayment() {
      try {
        localStorage.removeItem(PENDING_KEY);
      } catch (_) { }
    }

    async function handlePageShow(event) {
      try {
        const navigationEntries = performance.getEntriesByType("navigation");
        const navType = navigationEntries?.[0]?.type || "";
        if (event.persisted || navType === "back_forward") {
          setIsProcessingCheckout(false);
          const pending = getPendingPayment();
          if (pending?.orderId) {
            try {
              await addTransactionsApi({
                order_id: pending.orderId,
                status: "failed",
                is_reorder: isReorderMode ? "1" : "",
              });
            } catch (_) {
              // ignore network error; still move user ahead
            } finally {
              clearPendingPayment();
              router.push(
                `/payment-status?order_id=${pending.orderId}&status=failed`
              );
            }
          }
        }
      } catch (_) {
        setIsProcessingCheckout(false);
      }
    }

    window.addEventListener("pageshow", handlePageShow);
    return () => window.removeEventListener("pageshow", handlePageShow);
  }, [isReorderMode, router]);

  const stripeCurrencyCode = paymentSettings?.stripe_currency;

  const razorpayKey = paymentSettings?.razorpay_key;
  const razorpayCurrencyCode = paymentSettings?.razorpay_currency;

  const paystackCurrencyCode = paymentSettings?.paystack_currency;

  const [clientKey, setClientKey] = useState("");
  const [orderID, setOrderID] = useState();
  const [open, setOpen] = useState(false);

  const amount = currentCartProviderData?.overall_amount;

  const paymentMethods = [
    {
      method: "Stripe",
      methodIcon: stripe,
      methodType: "stripe",
      status: isPayOnlineAllowed ? paymentSettings?.stripe_status : "disable",
    },
    {
      method: "Paypal",
      methodIcon: paypal,
      methodType: "paypal",
      status: isPayOnlineAllowed ? paymentSettings?.paypal_status : "disable",
    },
    {
      method: "Paystack",
      methodIcon: paystack,
      methodType: "paystack",
      status: isPayOnlineAllowed ? paymentSettings?.paystack_status : "disable",
    },
    {
      method: "Razorpay",
      methodIcon: razorpay,
      methodType: "razorpay",
      status: isPayOnlineAllowed
        ? paymentSettings?.razorpayApiStatus
        : "disable",
    },
    {
      method: "Flutterwave",
      methodIcon: flutterwave,
      methodType: "flutterwave",
      status: isPayOnlineAllowed
        ? paymentSettings?.flutterwave_status
        : "disable",
    },
    {
      method: "Xendit",
      methodIcon: xendit,
      methodType: "xendit",
      status: isPayOnlineAllowed ? paymentSettings?.xendit_status : "disable",
    },
    {
      method: t("payOnService"),
      methodIcon: cod,
      methodType: "cod",
      status: isPayLaterAllowed ? paymentSettings?.cod_setting : "disable",
    },
  ];

  const enabledPaymentMethods = paymentMethods.filter(
    (method) =>
      method.status === "enable" || method.status === 1 || method.status === "1"
  );

  const onlinePaymentMethodsCount = enabledPaymentMethods.filter(
    (method) => method.methodType !== "cod"
  ).length;

  const customJobData = useSelector(selectCustomJobData);
  const isCustomJob = customJobData?.custom_job_request_id ? true : false;

  const appliedCoupon = useSelector(selectAppliedCoupon);

  const handleActiveNotes = () => setActiveNotes(true);

  const handleSaveNotes = () => {
    if (note === "") return toast.error(t("pleaseEnterNote"));

    dispatch(
      setDilveryDetails({
        ...dilveryDetails, // Keep the existing delivery details
        dilveryNote: note,
      })
    );
    setActiveNotes(false);
  };

  const handleClearNotes = () => {
    setActiveNotes(false);
    setNote(""); // Clear the note
    dispatch(
      setDilveryDetails({
        ...dilveryDetails, // Keep the existing delivery details
        dilveryNote: "",
      })
    );
  };

  const handleServiceType = (type) => {
    setServiceType(type);

    dispatch(
      setDilveryDetails({
        ...dilveryDetails,
        dilveryAddressType: type,
        ...(type === "store" ? { dilevryLocation: {} } : {}), // Reset location for store
      })
    );

    if (isReorderMode) {
      dispatch(
        setReorderMode({
          ...reorderState,
          provider: {
            ...reorderState.provider,
            dilveryAddressType: type,
            ...(type === "store" ? { dilevryLocation: {} } : {}), // Reset location for store
          },
        })
      );
    }
  };

  useEffect(() => {
    if (!dilveryDetails.dilveryAddressType) {
      if (availableOnHome && availableOnStore) {
        // If both are true, set to "home"
        setServiceType("home");
        dispatch(
          setDilveryDetails({
            ...dilveryDetails,
            dilveryAddressType: "home",
            // Convert dayjs object to string format
            dilveryDate: dilveryDetails?.dilveryDate
              ? dayjs(dilveryDetails.dilveryDate).format("YYYY-MM-DD")
              : "",
          })
        );
        if (isReorderMode) {
          dispatch(
            setReorderMode({
              ...reorderState,
              provider: {
                ...reorderState.provider,
                dilveryAddressType: "home",
                // Convert dayjs object to string format
                dilveryDate: reorderState.provider?.dilveryDate
                  ? dayjs(reorderState.provider.dilveryDate).format(
                    "YYYY-MM-DD"
                  )
                  : "",
              },
            })
          );
        }
      } else if (availableOnHome) {
        // If only home is available
        setServiceType("home");
        dispatch(
          setDilveryDetails({
            ...dilveryDetails,
            dilveryAddressType: "home",
            // Convert dayjs object to string format
            dilveryDate: dilveryDetails?.dilveryDate
              ? dayjs(dilveryDetails.dilveryDate).format("YYYY-MM-DD")
              : "",
          })
        );
      } else if (availableOnStore) {
        // If only store is available
        setServiceType("store");
        dispatch(
          setDilveryDetails({
            ...dilveryDetails,
            dilveryAddressType: "store",
            dilevryLocation: {},
            // Convert dayjs object to string format
            dilveryDate: dilveryDetails?.dilveryDate
              ? dayjs(dilveryDetails.dilveryDate).format("YYYY-MM-DD")
              : "",
          })
        );
      }
    }
  }, [availableOnHome, availableOnStore, dilveryDetails, reorderState]);

  useEffect(() => {
    // Automatically set the first available payment method if not already set
    if (
      !dilveryDetails.dilevryPymentMethod &&
      enabledPaymentMethods.length > 0
    ) {
      const firstMethod = enabledPaymentMethods[0].methodType;

      setPaymentOption(firstMethod);
      dispatch(
        setDilveryDetails({
          ...dilveryDetails,
          dilevryPymentMethod: firstMethod,
        })
      );
    }
  }, [enabledPaymentMethods, dispatch, dilveryDetails]);

  useEffect(() => {
    if (isReorderMode && reorderState.provider) {
      dispatch(
        setDilveryDetails({
          dilveryAddressType:
            reorderState.provider.at_store === "1" ? "store" : "home",
          dilevryLocation: {}, // Let user select new address
          dilveryDate: "", // Let user select new date/time
          dilveryTime: "",
          dilveryNote: "",
          dilevryPymentMethod: "", // Let user select payment method
          isReOrder: true,
          reOrderId: reorderState.orderId,
        })
      );
    }
  }, [isReorderMode, reorderState.provider]);

  const handleOpenOffersModal = () => {
    setOffersModalOpen(true);
  };

  const handleApply = async (offer) => {
    try {
      // Get subtotal and remove commas before sending to API
      const subtotalValue = showTax
        ? currentCartProviderData?.sub_total_without_tax
        : currentCartProviderData?.sub_total;
      const numericSubtotal = Number(String(subtotalValue || 0));

      const res = await validatePromocodeApi({
        promo_code_id: offer?.id,
        provider_id: currentCartProviderData?.provider_id,
        overall_amount: numericSubtotal,
      });
      if (res.error === false) {
        // Extract discount amount - handle both object and number formats
        let discountAmount = res.data[0]?.final_discount || res.data[0]?.discount || 0;

        // If discountAmount is an object (e.g., {final_total: 100}), extract the numeric value
        if (typeof discountAmount === 'object' && discountAmount !== null) {
          discountAmount = discountAmount.final_total || discountAmount.final_discount || 0;
        }

        // Ensure it's a number
        discountAmount = Number(discountAmount) || 0;

        // Store both discount amount and coupon details in Redux
        dispatch(setPromocodeDiscount(discountAmount));
        dispatch(setAppliedCoupon(offer));

        setOffersModalOpen(false);
        logClarityEvent(PAYMENT_EVENTS.PROMO_CODE_APPLIED, {
          promo_code_id: offer?.id,
          discount_value: discountAmount,
        });
      } else {
        toast.error(res.message);
        logClarityEvent(PAYMENT_EVENTS.PROMO_CODE_FAILED, {
          promo_code_id: offer?.id,
          reason: res.message,
        });
      }
    } catch (error) {
      console.log(error);
      logClarityEvent(PAYMENT_EVENTS.PROMO_CODE_FAILED, {
        promo_code_id: offer?.id,
        reason: "network_error",
      });
    }
  };

  const handleRemove = () => {
    dispatch(setAppliedCoupon(null)); // Clear the coupon in Redux
    dispatch(setPromocodeDiscount(0)); // Clear the discount
  };

  const handlePaymentOption = (method) => {
    if (dilveryDetails.dilevryPymentMethod !== method.methodType) {
      setPaymentOption(method.methodType);
      dispatch(
        setDilveryDetails({
          ...dilveryDetails,
          dilevryPymentMethod: method.methodType,
        })
      );
      logClarityEvent(PAYMENT_EVENTS.PAYMENT_METHOD_SELECTED, {
        method: method.methodType,
        provider_id: currentCartProviderData?.provider_id,
      });
    }
  };

  const fetchPromoCodes = async () => {
    try {
      const res = await getPromoCodeApi({
        partner_id: currentCartProviderData.provider_id,
      });
      if (res?.error === false) {
        setOffers(res?.data);
      } else {
        setOffers([]);
      }
    } catch (error) {
      console.log(error);
    }
  };

  const fetchAddress = async () => {
    // Fetch the default address
    try {
      const response = await getAddressApi();
      if (response.error === false) {
        setAddresses(response.data);
        // Find the default address and set it
        const defaultAddr = response.data.find(
          (address) => address.is_default === "1"
        );
        if (defaultAddr) {
          setDefaultAddress(defaultAddr);
          dispatch(
            setDilveryDetails({
              ...dilveryDetails, // Keep the existing delivery details
              dilevryLocation: defaultAddr, // Update dilevryLocation with the new address
            })
          );
        }
      }
    } catch (error) {
      console.log(error);
    }
  };

  useEffect(() => {
    if (currentCartProviderData?.provider_id) {
      fetchPromoCodes();
    }
  }, [currentCartProviderData?.provider_id]);

  useEffect(() => {
    if (serviceType === "home") {
      fetchAddress();
    }
  }, [serviceType]);

  // Handle Cash on Delivery (COD) Payment
  const handleCODPayment = async (dilveryDetails) => {
    try {
      const response = await placeOrderApi({
        method: dilveryDetails?.dilevryPymentMethod,
        date: dayjs(dilveryDetails?.dilveryDate).format("YYYY-MM-DD"),
        time: dilveryDetails?.dilveryTime,
        addressId:
          dilveryDetails?.dilveryAddressType === "home"
            ? dilveryDetails?.dilevryLocation?.id
            : "",
        order_note: dilveryDetails?.dilveryNote
          ? dilveryDetails?.dilveryNote
          : "",
        promo_code_id: appliedCoupon ? appliedCoupon?.id : "",
        at_store: dilveryDetails?.dilveryAddressType === "store" ? 1 : "",
        custom_job_request_id: isCustomJob
          ? customJobData?.custom_job_request_id
          : "",
        bidder_id: isCustomJob ? customJobData?.providerId : "",
        order_id: dilveryDetails?.reOrderId ? dilveryDetails?.reOrderId : "",
      });
      if (response.error === false) {
        const orderId = response?.data?.order_id;
        // Handle successful payment
        const res = await addTransactionsApi({
          order_id: orderId,
          status: "success",
          is_reorder: isReorderMode ? "1" : "",
        });

        if (res.error === false) {
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_SUCCEEDED, {
            payment_method: dilveryDetails?.dilevryPymentMethod,
            order_id: orderId,
          });
          logClarityEvent(BOOKING_EVENTS.BOOKING_CONFIRMED, {
            order_id: orderId,
            payment_method: dilveryDetails?.dilevryPymentMethod,
          });
          setIsProcessingCheckout(false);
          toast.success(t("paymentSuccessWithCOD"));

          if (isReorderMode) {
            dispatch(clearReorder());
          } else {
            dispatch(clearCart());
          }
          dispatch(clearChekoutData());
          router.push(`/booking/inv-${orderId}`);
        } else {
          setIsProcessingCheckout(false);
          toast.error(res.message || "Failed to update transaction status.");
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
            payment_method: dilveryDetails?.dilevryPymentMethod,
            order_id: orderId,
            reason: res.message,
          });
        }

        // Proceed to order confirmation
      } else {
        setIsProcessingCheckout(false);
        toast.error(response?.message);
        logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
          payment_method: dilveryDetails?.dilevryPymentMethod,
          reason: response?.message,
        });
      }
    } catch (error) {
      console.log(error);
      logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
        payment_method: dilveryDetails?.dilevryPymentMethod,
        reason: "cod_exception",
      });
    }
  };

  const calculateFinalAmount = () => {
    // Get the appropriate subtotal based on tax configuration
    const subtotalValue = showTax
      ? currentCartProviderData?.sub_total_without_tax
      : currentCartProviderData?.sub_total;
    // Remove commas and convert to number (backend sends values like "1,490.00")
    const subTotal = Number(String(subtotalValue || 0));
    const discount = Number(promocodeDiscount) || 0;
    const tax = Number(taxValue) || 0;


    const visitingCharges =
      serviceType === "home"
        ? Number(String(currentCartProviderData?.visiting_charges || 0))
        : 0;

    const finalAmount = subTotal - discount + tax + visitingCharges;
    return finalAmount;
  };

  const createPaymentIntent = async (order_id) => {
    try {
      const response = await createStripePaymentIntentApi({
        order_id: order_id,
      });

      if (response?.error === false) {
        setClientKey(response?.data?.client_secret);
        setOpen(true); // Open the Stripe payment modal
      } else {
        toast.error(response?.message || t("somethingWentWrong"));
        setIsProcessingCheckout(false);
      }
    } catch (error) {
      console.error("Error creating payment intent:", error);
      toast.error(t("somethingWentWrong"));
      setIsProcessingCheckout(false);
    }
  };

  // Handle Stripe Payment
  const handleStripePayment = async () => {
    try {
      const response = await placeOrderApi({
        method: dilveryDetails?.dilevryPymentMethod,
        date: dayjs(dilveryDetails?.dilveryDate).format("YYYY-MM-DD"),
        time: dilveryDetails?.dilveryTime,
        addressId:
          dilveryDetails?.dilveryAddressType === "home"
            ? dilveryDetails?.dilevryLocation?.id
            : "",
        order_note: dilveryDetails?.dilveryNote
          ? dilveryDetails?.dilveryNote
          : "",
        promo_code_id: appliedCoupon ? appliedCoupon?.id : "",
        at_store: dilveryDetails?.dilveryAddressType === "store" ? 1 : "",
        custom_job_request_id: isCustomJob
          ? customJobData?.custom_job_request_id
          : "",
        bidder_id: isCustomJob ? customJobData?.providerId : "",
        order_id: dilveryDetails?.reOrderId ? dilveryDetails?.reOrderId : "",
      });
      if (response?.error === false) {
        setOrderID(response.data.order_id);
        createPaymentIntent(response.data.order_id);

        // Handle Stripe modal close/cancel
        const handleStripeCancel = async () => {
          try {
            await addTransactionsApi({
              order_id: response.data.order_id,
              status: "failed",
            });
            setIsProcessingCheckout(false);
            router.push(
              `/payment-status?order_id=${response.data.order_id}&status=failed`
            );
          } catch (error) {
            console.error("Error updating cancelled transaction:", error);
            setIsProcessingCheckout(false);
            toast.error(t("somethingWentWrong"));
          }
        };

        // Add event listener for Stripe modal close
        const stripeModal = document.querySelector(".stripe-modal");
        if (stripeModal) {
          stripeModal.addEventListener("close", handleStripeCancel);
        }
      } else {
        setIsProcessingCheckout(false);
        toast.error(response?.message);
      }
    } catch (error) {
      setIsProcessingCheckout(false);
      toast.error(t("somethingWentWrong"));
    }
  };

  // Handle PayPal Payment
  const handlePaypalPayment = async () => {
    try {
      // Place the order first
      const orderResponse = await placeOrderApi({
        method: dilveryDetails?.dilevryPymentMethod, // Specify PayPal as the payment method
        date: dayjs(dilveryDetails?.dilveryDate).format("YYYY-MM-DD"),
        time: dilveryDetails?.dilveryTime,
        addressId:
          dilveryDetails?.dilveryAddressType === "home"
            ? dilveryDetails?.dilevryLocation?.id
            : "",
        order_note: dilveryDetails?.dilveryNote
          ? dilveryDetails?.dilveryNote
          : "",
        promo_code_id: appliedCoupon ? appliedCoupon?.id : "",
        at_store: dilveryDetails?.dilveryAddressType === "store" ? 1 : "",
        custom_job_request_id: isCustomJob
          ? customJobData?.custom_job_request_id
          : "",
        bidder_id: isCustomJob ? customJobData?.providerId : "",
        order_id: dilveryDetails?.reOrderId ? dilveryDetails?.reOrderId : "",
      });

      if (orderResponse.error === false) {
        const orderId = orderResponse.data.order_id;
        const paypalUrl = orderResponse.data.paypal_link; // Get PayPal URL from the response

        if (paypalUrl) {
          // Mark pending and open PayPal URL in the current window
          try {
            localStorage.setItem(
              "Servio_pending_payment",
              JSON.stringify({ orderId, method: "paypal", ts: Date.now() })
            );
          } catch (_) { }
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_GATEWAY_REDIRECTED, {
            payment_method: "paypal",
            order_id: orderId,
          });
          window.location.href = paypalUrl;
        } else {
          setIsProcessingCheckout(false); // Stop loading on error
          toast.error("PayPal URL not found in the response.");
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
            payment_method: "paypal",
            order_id: orderId,
            reason: "missing_gateway_url",
          });
        }
      } else {
        setIsProcessingCheckout(false); // Stop loading on error
        toast.error(orderResponse.message || "Failed to place order.");
        logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
          payment_method: "paypal",
          reason: orderResponse.message,
        });
      }
    } catch (error) {
      console.error("Error during PayPal payment:", error);
      setIsProcessingCheckout(false); // Stop loading on error
      toast.error(t("somethingWentWrong"));
      logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
        payment_method: "paypal",
        reason: "exception",
      });
    }
  };

  // Handle Paystack Payment
  const handlePaystackPayment = async () => {
    try {
      const orderResponse = await placeOrderApi({
        method: "paystack",
        date: dayjs(dilveryDetails?.dilveryDate).format("YYYY-MM-DD"),
        time: dilveryDetails?.dilveryTime,
        addressId:
          dilveryDetails?.dilveryAddressType === "home"
            ? dilveryDetails?.dilevryLocation?.id
            : "",
        order_note: dilveryDetails?.dilveryNote
          ? dilveryDetails?.dilveryNote
          : "",
        promo_code_id: appliedCoupon ? appliedCoupon?.id : "",
        at_store: dilveryDetails?.dilveryAddressType === "store" ? 1 : "",
        custom_job_request_id: isCustomJob
          ? customJobData?.custom_job_request_id
          : "",
        bidder_id: isCustomJob ? customJobData?.providerId : "",
        order_id: dilveryDetails?.reOrderId ? dilveryDetails?.reOrderId : "",
      });

      if (orderResponse.error === false) {
        const orderId = orderResponse.data.order_id;

        if (!userEmail) {
          setIsProcessingCheckout(false);
          toast.error(
            t("pleaseUpdateYourEmailAddressToProceedWithPaystackPayment")
          );
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
            payment_method: "paystack",
            order_id: orderId,
            reason: "missing_email",
          });
          return;
        }

        // Define Paystack success and close handlers
        const onSuccess = async (reference) => {
          try {
            const transactionResponse = await addTransactionsApi({
              order_id: orderId,
              status: "success",
              is_reorder: isReorderMode ? "1" : "",
            });

            if (transactionResponse.error === false) {
              toast.success(t("paymentSuccess"));
              router.push(
                `/payment-status?order_id=${orderId}&status=successful`
              );
              logClarityEvent(PAYMENT_EVENTS.PAYMENT_SUCCEEDED, {
                payment_method: "paystack",
                order_id: orderId,
              });
              logClarityEvent(BOOKING_EVENTS.BOOKING_CONFIRMED, {
                order_id: orderId,
                payment_method: "paystack",
              });
            } else {
              toast.error(
                transactionResponse.message ||
                "Failed to update transaction status."
              );
              logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
                payment_method: "paystack",
                order_id: orderId,
                reason: transactionResponse.message,
              });
            }
          } catch (error) {
            console.error("Error updating transaction:", error);
            toast.error(t("somethingWentWrong"));
            logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
              payment_method: "paystack",
              order_id: orderId,
              reason: "transaction_update_exception",
            });
          }
        };

        const onClose = async () => {
          try {
            await addTransactionsApi({
              order_id: orderId,
              status: "failed",
            });
            setIsProcessingCheckout(false);
            // Redirect to payment failed page without showing toast
            router.push(`/payment-status?order_id=${orderId}&status=failed`);
            logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
              payment_method: "paystack",
              order_id: orderId,
              reason: "gateway_closed",
            });
          } catch (error) {
            console.error("Error updating transaction:", error);
            setIsProcessingCheckout(false);
            toast.error("Something went wrong. Please try again.");
            logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
              payment_method: "paystack",
              order_id: orderId,
              reason: "gateway_close_exception",
            });
          }
        };

        // Initialize Paystack payment
        const paystack = new PaystackPop();
        paystack.newTransaction({
          key: paymentSettings?.paystack_key,
          email: userEmail,
          amount: calculateFinalAmount() * 100,
          currency: paystackCurrencyCode,
          reference: `order_${orderId}_${new Date().getTime()}`,
          metadata: {
            order_id: orderId,
          },
          onSuccess,
          onClose,
        });
      } else {
        setIsProcessingCheckout(false);
        toast.error(orderResponse.message || "Failed to place order.");
        logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
          payment_method: "paystack",
          reason: orderResponse.message,
        });
      }
    } catch (error) {
      console.error("Error during Paystack payment:", error);
      setIsProcessingCheckout(false);
      toast.error("Something went wrong. Please try again.");
      logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
        payment_method: "paystack",
        reason: "exception",
      });
    }
  };
  // Handle Razorpay Payment
  const handleRazorpayPayment = async () => {
    try {
      const orderResponse = await placeOrderApi({
        method: dilveryDetails?.dilevryPymentMethod,
        date: dayjs(dilveryDetails?.dilveryDate).format("YYYY-MM-DD"),
        time: dilveryDetails?.dilveryTime,
        addressId:
          dilveryDetails?.dilveryAddressType === "home"
            ? dilveryDetails?.dilevryLocation?.id
            : "",
        order_note: dilveryDetails?.dilveryNote
          ? dilveryDetails?.dilveryNote
          : "",
        promo_code_id: appliedCoupon ? appliedCoupon?.id : "",
        at_store: dilveryDetails?.dilveryAddressType === "store" ? 1 : "",
        custom_job_request_id: isCustomJob
          ? customJobData?.custom_job_request_id
          : "",
        bidder_id: isCustomJob ? customJobData?.providerId : "",
        order_id: dilveryDetails?.reOrderId ? dilveryDetails?.reOrderId : "",
      });

      if (orderResponse.error === false) {
        const placeOrderId = orderResponse.data.order_id;

        const razorpayOrderResponse = await createRazorOrderApi({
          orderId: placeOrderId,
        });

        if (razorpayOrderResponse.error === false) {
          const razorpayOrderId = razorpayOrderResponse.data.id;

          // Razorpay options
          const options = {
            key: razorpayKey,
            amount: parseInt(calculateFinalAmount()) * 100,
            currency: razorpayCurrencyCode,
            name: process.env.NEXT_PUBLIC_APP_NAME,
            order_id: razorpayOrderId,
            notes: { order_id: placeOrderId },
            description: "Payment for Your Product",
            handler: async function (response) {
              if (response.razorpay_payment_id) {
                await addTransactionsApi({
                  order_id: placeOrderId,
                  status: "success",
                  is_reorder: isReorderMode ? "1" : "",
                })
                  .then((res) => {
                    if (res.error === false) {
                      toast.success(t("paymentSuccessful"));
                      router.push(
                        `/payment-status?order_id=${placeOrderId}&status=successful`
                      );
                      logClarityEvent(PAYMENT_EVENTS.PAYMENT_SUCCEEDED, {
                        payment_method: "razorpay",
                        order_id: placeOrderId,
                      });
                      logClarityEvent(BOOKING_EVENTS.BOOKING_CONFIRMED, {
                        order_id: placeOrderId,
                        payment_method: "razorpay",
                      });
                    } else {
                      toast.error(
                        res.message || "Failed to update transaction status."
                      );
                      logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
                        payment_method: "razorpay",
                        order_id: placeOrderId,
                        reason: res.message,
                      });
                    }
                  })
                  .catch((error) => {
                    console.error("Error updating transaction:", error);
                    toast.error(t("somethingWentWrong"));
                    logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
                      payment_method: "razorpay",
                      order_id: placeOrderId,
                      reason: "transaction_update_exception",
                    });
                  });
              }
            },
            theme: {
              color: "#3399cc",
            },
            modal: {
              ondismiss: async function () {
                try {
                  await addTransactionsApi({
                    order_id: placeOrderId,
                    status: "failed",
                  });
                  setIsProcessingCheckout(false);
                  // toast.error(t("paymentCancelled"));
                  router.push(
                    `/payment-status?order_id=${placeOrderId}&status=failed`
                  );
                  logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
                    payment_method: "razorpay",
                    order_id: placeOrderId,
                    reason: "gateway_dismissed",
                  });
                } catch (error) {
                  console.error("Error updating transaction:", error);
                  setIsProcessingCheckout(false);
                  toast.error(t("somethingWentWrong"));
                  logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
                    payment_method: "razorpay",
                    order_id: placeOrderId,
                    reason: "gateway_dismiss_exception",
                  });
                }
              },
            },
          };

          // Add event listener for browser back button/escape key
          const handleModalClose = async () => {
            try {
              await addTransactionsApi({
                order_id: placeOrderId,
                status: "failed",
              });
              setIsProcessingCheckout(false);
              router.push(
                `/payment-status?order_id=${placeOrderId}&status=failed`
              );
            } catch (error) {
              console.error("Error updating transaction:", error);
              setIsProcessingCheckout(false);
              toast.error(t("somethingWentWrong"));
            }
          };

          window.addEventListener("popstate", handleModalClose);
          window.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
              handleModalClose();
            }
          });

          const razorpayInstance = new window.Razorpay(options);
          razorpayInstance.open();
        } else {
          setIsProcessingCheckout(false);
          toast.error(
            razorpayOrderResponse.message || "Failed to create Razorpay order."
          );
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
            payment_method: "razorpay",
            order_id: placeOrderId,
            reason: razorpayOrderResponse.message,
          });
        }
      } else {
        setIsProcessingCheckout(false); // Stop loading on error
        toast.error(orderResponse.message || "Failed to place order.");
        logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
          payment_method: "razorpay",
          reason: orderResponse.message,
        });
      }
    } catch (error) {
      console.error("Error during Razorpay payment:", error);
      setIsProcessingCheckout(false); // Stop loading on error
      toast.error(t("somethingWentWrong"));
      logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
        payment_method: "razorpay",
        reason: "exception",
      });
    }
  };

  // Handle Flutterwave Payment
  const handleFlutterwavePayment = async () => {
    try {
      // Place the order first
      const orderResponse = await placeOrderApi({
        method: dilveryDetails?.dilevryPymentMethod, // Specify flutterwaveUrl as the payment method
        date: dayjs(dilveryDetails?.dilveryDate).format("YYYY-MM-DD"),
        time: dilveryDetails?.dilveryTime,
        addressId:
          dilveryDetails?.dilveryAddressType === "home"
            ? dilveryDetails?.dilevryLocation?.id
            : "",
        order_note: dilveryDetails?.dilveryNote
          ? dilveryDetails?.dilveryNote
          : "",
        promo_code_id: appliedCoupon ? appliedCoupon?.id : "",
        at_store: dilveryDetails?.dilveryAddressType === "store" ? 1 : "",
        custom_job_request_id: isCustomJob
          ? customJobData?.custom_job_request_id
          : "",
        bidder_id: isCustomJob ? customJobData?.providerId : "",
        order_id: dilveryDetails?.reOrderId ? dilveryDetails?.reOrderId : "",
      });

      if (orderResponse.error === false) {
        const orderId = orderResponse.data.order_id;
        const flutterwaveUrl = orderResponse.data.flutterwave; // Get flutterwaveUrl URL from the response

        if (flutterwaveUrl) {
          // Mark pending and open Flutterwave URL in the current window
          try {
            localStorage.setItem(
              "Servio_pending_payment",
              JSON.stringify({ orderId, method: "flutterwave", ts: Date.now() })
            );
          } catch (_) { }
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_GATEWAY_REDIRECTED, {
            payment_method: "flutterwave",
            order_id: orderId,
          });
          window.location.href = flutterwaveUrl;
        } else {
          setIsProcessingCheckout(false); // Stop loading on error
          toast.error("Flutterwave URL not found in the response.");
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
            payment_method: "flutterwave",
            order_id: orderId,
            reason: "missing_gateway_url",
          });
        }
      } else {
        setIsProcessingCheckout(false); // Stop loading on error
        toast.error(orderResponse.message || "Failed to place order.");
        logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
          payment_method: "flutterwave",
          reason: orderResponse.message,
        });
      }
    } catch (error) {
      console.error("Error during Flutterwave payment:", error);
      setIsProcessingCheckout(false); // Stop loading on error
      toast.error(t("somethingWentWrong"));
      logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
        payment_method: "flutterwave",
        reason: "exception",
      });
    }
  };

  const handleXenditPayment = async () => {
    try {
      // Place the order first
      const orderResponse = await placeOrderApi({
        method: dilveryDetails?.dilevryPymentMethod, // Specify xenditUrl as the payment method
        date: dayjs(dilveryDetails?.dilveryDate).format("YYYY-MM-DD"),
        time: dilveryDetails?.dilveryTime,
        addressId:
          dilveryDetails?.dilveryAddressType === "home"
            ? dilveryDetails?.dilevryLocation?.id
            : "",
        order_note: dilveryDetails?.dilveryNote
          ? dilveryDetails?.dilveryNote
          : "",
        promo_code_id: appliedCoupon ? appliedCoupon?.id : "",
        at_store: dilveryDetails?.dilveryAddressType === "store" ? 1 : "",
        custom_job_request_id: isCustomJob
          ? customJobData?.custom_job_request_id
          : "",
        bidder_id: isCustomJob ? customJobData?.providerId : "",
        order_id: dilveryDetails?.reOrderId ? dilveryDetails?.reOrderId : "",
      });

      if (orderResponse.error === false) {
        const orderId = orderResponse.data.order_id;
        const xenditUrl = orderResponse.data.xendit; // Get xenditUrl URL from the response

        if (xenditUrl) {
          // Mark pending and open Xendit URL in the current window
          try {
            localStorage.setItem(
              "Servio_pending_payment",
              JSON.stringify({ orderId, method: "xendit", ts: Date.now() })
            );
          } catch (_) { }
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_GATEWAY_REDIRECTED, {
            payment_method: "xendit",
            order_id: orderId,
          });
          window.location.href = xenditUrl;
        } else {
          setIsProcessingCheckout(false); // Stop loading on error
          toast.error(t("xenditUrlNotFoundInResponse"));
          logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
            payment_method: "xendit",
            order_id: orderId,
            reason: "missing_gateway_url",
          });
        }
      } else {
        setIsProcessingCheckout(false); // Stop loading on error
        toast.error(orderResponse.message || "Failed to place order.");
        logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
          payment_method: "xendit",
          reason: orderResponse.message,
        });
      }
    } catch (error) {
      console.error("Error during Xendit payment:", error);
      setIsProcessingCheckout(false); // Stop loading on error
      toast.error(t("somethingWentWrong"));
      logClarityEvent(PAYMENT_EVENTS.PAYMENT_FAILED, {
        payment_method: "xendit",
        reason: "exception",
      });
    }
  };

  const proceedToPayment = (paymentMethod, dilveryDetails) => {
    try {
      logClarityEvent(BOOKING_EVENTS.BOOKING_REQUESTED, {
        payment_method: paymentMethod,
        provider_id: currentCartProviderData?.provider_id,
        amount: calculateFinalAmount(),
        is_reorder: dilveryDetails?.isReOrder ? "1" : "0",
      });
      logClarityEvent(PAYMENT_EVENTS.PAYMENT_STARTED, {
        payment_method: paymentMethod,
        amount: calculateFinalAmount(),
      });
      switch (paymentMethod) {
        case "cod":
          handleCODPayment(dilveryDetails);
          break;
        case "stripe":
          handleStripePayment();
          break;
        case "paypal":
          handlePaypalPayment();
          break;
        case "paystack":
          handlePaystackPayment();
          break;
        case "razorpay":
          handleRazorpayPayment();
          break;
        case "flutterwave":
          handleFlutterwavePayment();
          break;
        case "xendit":
          handleXenditPayment();
          break;
        default:
          setIsProcessingCheckout(false); // Reset on invalid payment method
          toast.error(t("invalidPaymentMethodSelected"));
      }
    } catch (error) {
      console.error("Error in payment processing:", error);
      setIsProcessingCheckout(false); // Reset on error
      toast.error(t("somethingWentWrong"));
    }
  };

  const handleCheckout = async (e) => {
    e.preventDefault();
    setIsProcessingCheckout(true); // Start loading

    try {
      const {
        dilevryLocation,
        dilevryPymentMethod,
        dilveryAddressType,
        dilveryDate,
        dilveryTime,
        dilveryNote,
        isReOrder,
        reOrderId,
      } = dilveryDetails;

      // Validation checks
      if (dilveryAddressType === "home") {
        if (
          !dilevryLocation ||
          !dilevryLocation.id ||
          !dilevryLocation.address
        ) {
          toast.error(t("invalidDeliveryAddress"));
          setIsProcessingCheckout(false); // Stop loading on error
          return;
        }
      }

      if (!["home", "store"].includes(dilveryAddressType)) {
        toast.error(t("invalidDelievryType"));
        setIsProcessingCheckout(false);
        return;
      }

      if (!dilveryDate || !dilveryTime) {
        toast.error(t("invalidDateAndTime"));
        setIsProcessingCheckout(false);
        return;
      }

      if (!dilevryPymentMethod) {
        toast.error(t("selectPaymentMethod"));
        setIsProcessingCheckout(false);
        return;
      }

      // For 'home' delivery, validate address and check provider availability
      if (dilveryAddressType === "home") {
        try {
          const response = await providerAvailableApi({
            latitude: dilevryLocation?.lattitude,
            longitude: dilevryLocation?.longitude,
            isCheckout: 1,
            order_id: isReorderMode ? reorderState.orderId : "",
            custom_job_request_id: isCustomJob
              ? customJobData?.custom_job_request_id
              : "",
            bidder_id: isCustomJob ? customJobData?.providerId : "",
          });

          if (response.error === false) {
            await proceedToPayment(dilevryPymentMethod, dilveryDetails);
          } else {
            toast.error(response.message);
            setIsProcessingCheckout(false);
          }
        } catch (error) {
          console.error(error);
          toast.error(t("somethingWentWrong"));
          setIsProcessingCheckout(false);
        }
      } else {
        // For 'store' delivery
        await proceedToPayment(dilevryPymentMethod, dilveryDetails);
      }
    } catch (error) {
      console.error(error);
      toast.error(t("somethingWentWrong"));
      setIsProcessingCheckout(false);
    } finally {
      setIsProcessingCheckout(false);
    }
  };

  return (
    <Layout>
      <BreadCrumb
        firstEle={t("cart")}
        firstEleLink="/cart"
        secEle={t("checkout")}
        SecEleLink="/checkout"
      />
      <section className="check-out my-12 container mx-auto">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <div className="col-span-12 lg:col-span-8">
            <span className="text-3xl font-semibold">{t("checkout")}</span>
            <div className="mt-6 border rounded-[18px] p-6">
              {/* Services Performs At */}
              <div className="mb-6">
                <span className="text-xl lg:text-2xl font-semibold">
                  {t("servicePerformAt")}
                </span>
                <div className="flex flex-wrap sm:flex-nowrap mt-[18px] w-full gap-3">
                  {/* home Button */}
                  {availableOnHome && (
                    <button
                      onClick={() => handleServiceType("home")}
                      className={`disabled:cursor-not-allowed flex items-center justify-between m-0 px-4 py-2 border rounded-[8px] w-full transition-all duration-300 ease-in-out ${serviceType === "home"
                        ? "border_color selected_shadow"
                        : "border-gray-300"
                        }`}
                      disabled={serviceType === "home"}
                    >
                      <div className="flex items-center">
                        <span>
                          <BsHouse size={24} />
                        </span>
                        <span className="ml-2 rtl:mr-2">{t("atDoorstep")}</span>
                      </div>
                      <div className="flex items-center justify-center">
                        <div className="relative">
                          {/* Outer Blue Circle */}
                          <div
                            className={`w-6 h-6 rounded-full flex items-center justify-center transition-all duration-300 ease-in-out ${serviceType === "home"
                              ? "primary_bg_color"
                              : "bg-[#21212138]"
                              }`}
                          >
                            {/* Inner White Circle */}
                            <div className="w-2 h-2 bg-white rounded-full"></div>
                          </div>
                        </div>
                      </div>
                    </button>
                  )}
                  {/* store Button */}
                  {availableOnStore && (
                    <button
                      onClick={() => handleServiceType("store")}
                      className={`disabled:cursor-not-allowed flex items-center justify-between m-0 px-4 py-2 border rounded-[8px] w-full transition-all duration-300 ease-in-out ${serviceType === "store"
                        ? "border_color selected_shadow"
                        : "border-gray-300"
                        }`}
                      disabled={serviceType === "store"}
                    >
                      <div className="flex items-center gap-3">
                        <span>
                          <IoStorefrontOutline size={24} />
                        </span>
                        <span className="rtl:mr-2">{t("atStore")}</span>
                      </div>
                      <div className="flex items-center justify-center">
                        <div className="relative">
                          {/* Outer Blue Circle */}
                          <div
                            className={`w-6 h-6 rounded-full flex items-center justify-center transition-all duration-300 ease-in-out ${serviceType === "store"
                              ? "primary_bg_color"
                              : "bg-[#21212138]"
                              }`}
                          >
                            {/* Inner White Circle */}
                            <div className="w-2 h-2 bg-white rounded-full"></div>
                          </div>
                        </div>
                      </div>
                    </button>
                  )}
                </div>
              </div>

              {/* Address */}
              {serviceType === "home" && (
                <div className="mb-6">
                  <span className="text-xl lg:text-2xl font-semibold">
                    {t("address")}
                  </span>
                  {defaultAddress && addresses.length > 0 ? (
                    <div className="rounded-md flex items-start space-x-3 mt-[18px]">
                      <span className="primary_text_color light_bg_color p-3 rounded-[8px] flex items-center justify-center">
                        <FaLocationDot />
                      </span>
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <span className="block text-lg font-semibold">
                            {defaultAddress?.city_name || defaultAddress?.city}
                          </span>
                          <span>|</span>
                          <span>
                            {" "}
                            <button
                              className="primary_text_color underline"
                              onClick={() => setAddressDrawerOpen(true)}
                            >
                              {t("edit")}
                            </button>
                          </span>
                        </div>
                        <span className="block text-base font-normal description_color break-all">
                          {defaultAddress?.address}, {""} {defaultAddress?.area}
                          <br />
                          {defaultAddress?.type} <br />
                          {defaultAddress?.mobile}
                        </span>
                      </div>
                    </div>
                  ) : (
                    <button
                      onClick={() => setAddressDrawerOpen(true)}
                      className="mt-2 w-full border border-dashed border_color flex items-center justify-center gap-3 primary_text_color p-4 rounded-xl"
                    >
                      <span>
                        <FaCirclePlus size={22} />
                      </span>
                      <span>{t("addAddress")}</span>
                    </button>
                  )}
                </div>
              )}

              {/* Schedule At */}
              <div className="mb-6">
                <span className="text-xl lg:text-2xl font-semibold">
                  {t("scheduleAt")}
                </span>
                <div className="mt-3 flex flex-wrap sm:flex-nowrap items-center p-3 gap-3 w-full">
                  <div className="flex flex-col items-start justify-start gap-3 w-full">
                    <div className="flex flex-col md:flex-row items-center gap-3 w-full">
                      <div className="flex items-center  space-x-2 gap-3 w-full">
                        <span
                          className={`${dilveryDetails?.dilveryDate
                            ? "light_bg_color primary_text_color"
                            : "bg-[#2121212E]"
                            } p-3 rounded-[8px]`}
                        >
                          <BsCalendar3Week size={22} />
                        </span>
                        <div className="flex flex-col items-start justify-center">
                          <span className="text-base font-normal description_color">
                            {t("date")}
                          </span>
                          <span>
                            {dilveryDetails?.dilveryDate
                              ? dayjs(dilveryDetails.dilveryDate).format(
                                "DD/MM/YYYY"
                              )
                              : "---"}
                          </span>
                        </div>
                      </div>
                      <div className="flex items-center  space-x-2 gap-3 w-full">
                        <span
                          className={`${dilveryDetails?.dilveryTime
                            ? "light_bg_color primary_text_color"
                            : "bg-[#2121212E]"
                            } p-3 rounded-[8px]`}
                        >
                          <IoTimeOutline size={22} />
                        </span>
                        <div className="flex flex-col items-start justify-center">
                          <span className="text-base font-normal description_color">
                            {t("time")}
                          </span>
                          <span>
                            {dilveryDetails?.dilveryTime
                              ? dayjs(
                                `1970-01-01T${dilveryDetails.dilveryTime.replace(
                                  "-",
                                  ":"
                                )}`
                              ).format("h:mm A")
                              : "---"}{" "}
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Display time slot message if available */}
                    {dilveryDetails?.dilveryTimeMessage && (
                      <span className="w-full text-center text-sm bg-[#FFEEC5] p-2 rounded-md text-[#B39651] dark:bg-[#FFDA7F] mt-1 flex items-center justify-center gap-1">
                        <FaInfoCircle size={16} />
                        {dilveryDetails.dilveryTimeMessage}
                      </span>
                    )}
                  </div>

                  {dilveryDetails && dilveryDetails?.dilveryDate ? (
                    <button
                      className="px-4 py-2 border  rounded-md w-full light_bg_color border_color"
                      onClick={() => setScheduleDrawerOpen(true)}
                    >
                      <span className="primary_text_color flex items-center justify-center gap-1">
                        <span>{t("change")}</span> <BiSolidEdit size={22} />
                      </span>
                    </button>
                  ) : (
                    <button
                      className="px-4 py-2 border  rounded-md border-black w-full transition-all duration-300 hover:primary_bg_color hover:border_color hover:text-white"
                      onClick={() => setScheduleDrawerOpen(true)}
                    >
                      <span>{t("selectHere")}</span>
                    </button>
                  )}
                </div>

                {/* Notes Section */}
                <div className="extraNotes mt-3">
                  <div
                    className={`overflow-hidden transition-[max-height,opacity] duration-500 ease-in-out ${activeNotes
                      ? "max-h-[200px] opacity-100"
                      : "max-h-0 opacity-0"
                      }`}
                  >
                    <div className="flex  items-center justify-between gap-3">
                      <div className="flex items-center justify-between light_bg_color border border_color w-full rounded-lg p-3">
                        <input
                          type="text"
                          value={note}
                          onChange={(e) => setNote(e.target.value)}
                          placeholder={t("typeHere")}
                          className="w-full focus:outline-none bg-transparent"
                        />
                        <span>
                          <MdClose
                            size={22}
                            className="bg-transparent description_color cursor-pointer"
                            onClick={handleClearNotes}
                          />
                        </span>
                      </div>
                      <button
                        className="light_bg_color primary_text_color rounded-lg text-white p-3 w-2/5"
                        onClick={handleSaveNotes}
                      >
                        {t("save")}
                      </button>
                    </div>
                  </div>
                  {!activeNotes && (
                    <button
                      className={`mt-4 flex items-center ${note ? "justify-start" : "justify-center"
                        } p-3 rounded-md bg-[#2121212E] w-full gap-2`}
                      onClick={handleActiveNotes}
                    >
                      <MdModeEdit size={22} />
                      <span className="text-sm font-normal">
                        {note ? note : t("addInstruction")}
                      </span>
                    </button>
                  )}
                </div>
              </div>

              {/* Select Payment Option */}
              {enabledPaymentMethods.length > 0 ? (
                <div>
                  <span className="text-2xl font-semibold">
                    {t("selectPaymentOption")}
                  </span>
                  <div className="mt-4 grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                    {enabledPaymentMethods.map((method, index) => (
                      <button
                        key={index}
                        onClick={() => handlePaymentOption(method)}
                        className={`flex items-center justify-between gap-3 px-4 py-3 border rounded-md w-full transition-all duration-300 ${paymentOption === method.methodType
                          ? "border border_color selected_shadow"
                          : "border"
                          }`}
                      >
                        <div className="flex items-center gap-3">
                          <CustomImageTag
                            src={
                              method.methodType !== "cod" &&
                                onlinePaymentMethodsCount === 1
                                ? card
                                : method.methodIcon
                            }
                            alt={method.method}
                            className="w-8 aspect-square object-contain"
                            imgClassName="object-contain"
                          />
                          <span>
                            {method.methodType === "cod"
                              ? method.method
                              : onlinePaymentMethodsCount === 1
                                ? t("payNow")
                                : method.method}
                          </span>
                        </div>
                        <div className="flex items-center justify-center">
                          <div className="relative">
                            <div
                              className={`w-6 h-6 rounded-full flex items-center justify-center transition-all duration-300 ease-in-out ${paymentOption === method.methodType
                                ? "primary_bg_color"
                                : "bg-[#21212138]"
                                }`}
                            >
                              <div className="w-2 h-2 bg-white rounded-full"></div>
                            </div>
                          </div>
                        </div>
                      </button>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="text-red-500 text-center flex justify-center items-center">
                  {t("noPaymentMethodsAvailable")}
                </div>
              )}
            </div>
          </div>

          <div className="col-span-12 lg:col-span-4">
            <span className="text-3xl font-semibold">{t("Summary")}</span>
            {/* Offers */}
            {!isCustomJob && offers.length > 0 && (
              <>
                {appliedCoupon && Object.keys(appliedCoupon).length > 0 ? (
                  <div className="border border_color rounded-xl flex items-center justify-between gap-3 px-2 py-3 my-6">
                    <div className="flex items-center gap-3 w-10/12">
                      <div className="relative aspect-square w-16 flex-shrink-0">
                        <CustomImageTag
                          src={appliedCoupon?.image}
                          alt={appliedCoupon?.promo_code}
                          className="w-full h-full rounded-md"
                          imgClassName="rounded-md"
                        />
                      </div>
                      <div className="flex flex-col items-start justify-between mb-2">
                        {/* Title */}
                        <h3 className="primary_color font-bold text-lg">
                          {appliedCoupon?.promo_code}
                        </h3>
                        <p className="description_color text-sm line-clamp-2">
                          {appliedCoupon?.message}
                        </p>
                      </div>
                    </div>
                    <div className="transition-all duration-150">
                      <button
                        onClick={() => handleRemove()}
                        className="light_bg_color primary_text_color text-base font-normal px-4 py-1 rounded-md dark:bg-white"
                      >
                        {t("remove")}
                      </button>
                    </div>
                  </div>
                ) : offers.length > 1 ? (
                  <div
                    className="flex items-center justify-between mt-6 bg-green-100 text-green-600 p-3 rounded-md mb-4"
                    onClick={handleOpenOffersModal}
                  >
                    <span className="flex items-center gap-2">
                      <FaPercentage />
                      {t("saveBigwith")} {offers?.length} {t("moreOffers")}
                    </span>
                    <span className="text-green-600 font-semibold rtl:rotate-180">
                      <FaChevronRight size={20} />
                    </span>
                  </div>
                ) : (
                  <div className="border border_color rounded-xl flex items-center justify-between gap-3 px-2 py-3 my-6">
                    <div className="flex items-center gap-3 w-10/12">
                      <div className="relative aspect-square w-16 flex-shrink-0">
                        <CustomImageTag
                          src={offers[0]?.image}
                          alt={offers[0]?.promo_code}
                          className="w-full h-full rounded-md"
                          imgClassName="rounded-md"
                        />
                      </div>
                      <div className="flex flex-col items-start justify-between mb-2">
                        {/* Title */}
                        <h3 className="primary_color font-bold text-lg">
                          {offers[0]?.promo_code}
                        </h3>
                        <p className="description_color text-sm line-clamp-2">
                          {offers[0]?.message}
                        </p>
                      </div>
                    </div>
                    <div className="transition-all duration-150">
                      <button
                        onClick={() => handleApply(offers[0])}
                        className="light_bg_color primary_text_color text-base font-normal px-4 py-1 rounded-md dark:bg-white"
                      >
                        {t("apply")}
                      </button>
                    </div>
                  </div>
                )}
              </>
            )}

            {/* Total */}
            <div className="border light_bg_color p-5 rounded-xl my-6">
              <div className="flex flex-col gap-6">
                <div className="flex justify-between items-center text-base">
                  <span>
                    {isCustomJob ? t("customJobPrice") : t("subTotal")}
                  </span>
                  <span className="font-semibold">
                    {showTax
                      ? showPrice(currentCartProviderData?.sub_total_without_tax || currentCartProviderData?.sub_total)
                      : showPrice(currentCartProviderData?.sub_total)}
                  </span>
                </div>
                {showTax && taxValue !== "" && Number(taxValue) > 0 && (
                  <div className="flex justify-between items-center text-base">
                    <span>{t("tax")}</span>
                    <span className="font-semibold">
                      +{showPrice(Number(taxValue))}
                    </span>
                  </div>
                )}
                {!isCustomJob && promocodeDiscount > 0 && (
                  <div className="flex justify-between items-center text-base">
                    <span>{t("discount")}</span>
                    <span className="font-semibold">
                      -{showPrice(promocodeDiscount)}
                    </span>
                  </div>
                )}
                {serviceType === "home" &&
                  currentCartProviderData?.visiting_charges > 0 && (
                    <div className="flex justify-between items-center text-base">
                      <span>{t("vistingCharges")}</span>
                      <span className="font-semibold">
                        +{showPrice(currentCartProviderData?.visiting_charges)}
                      </span>
                    </div>
                  )}
              </div>
              <hr className="border-gray-300 my-6" />
              <div className="flex justify-between items-center text-lg font-bold">
                <span>{t("finalPrice")}</span>
                <span>{showPrice(calculateFinalAmount())}</span>
              </div>
              {/* Checkout Button */}
              <button
                disabled={isProcessingCheckout}
                className={`w-full primary_bg_color mt-6 text-white py-2 rounded-xl font-medium text-sm transition hover:bg-black ${isProcessingCheckout ? "opacity-70 cursor-not-allowed" : ""
                  }`}
                onClick={handleCheckout}
              >
                {isProcessingCheckout ? (
                  <div className="flex items-center justify-center">
                    <MiniLoader color="white" />
                  </div>
                ) : paymentOption === "cod" ? (
                  t("bookService")
                ) : isRepayment ? (
                  t("retryPayment")
                ) : (
                  t("makeOnlinePayment")
                )}
              </button>
            </div>
          </div>
        </div>
      </section>

      {scheduleDrawerOpen && (
        <SelectDateAndTimeDrawer
          dilveryDetails={dilveryDetails}
          open={scheduleDrawerOpen}
          providerId={currentCartProviderData?.provider_id}
          customJobId={customJobData?.custom_job_request_id}
          onClose={() => setScheduleDrawerOpen(false)}
        />
      )}
      {addressDrawerOpen && (
        <AddressDrawer
          addresses={addresses}
          setAddresses={setAddresses}
          open={addressDrawerOpen}
          onClose={() => setAddressDrawerOpen(false)}
          defaultAddress={defaultAddress}
          setDefaultAddress={setDefaultAddress}
          onUpdateAddress={() => { }}
        />
      )}

      {offersModalOpen && (
        <OfferModal
          offers={offers}
          open={offersModalOpen}
          close={() => setOffersModalOpen(false)}
          handleApply={handleApply}
          handleRemove={handleRemove}
          isApplied={(offer) => appliedCoupon && appliedCoupon.id === offer.id}
        />
      )}

      {dilveryDetails?.dilevryPymentMethod === "stripe" && (
        <StripePayment
          t={t}
          clientKey={clientKey}
          amount={amount}
          orderID={orderID}
          open={open}
          setOpen={setOpen}
          setIsProcessingCheckout={setIsProcessingCheckout}
        />
      )}
    </Layout>
  );
};

export default withAuth(Checkout);
