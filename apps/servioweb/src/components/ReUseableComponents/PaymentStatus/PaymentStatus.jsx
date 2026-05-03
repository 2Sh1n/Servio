"use client";
import React, { useEffect } from "react";
import { useRouter } from "next/router";
import Lottie from "lottie-react";
import successAnimation from "../../../../public/animations/success.json";
import failedAnimation from "../../../../public/animations/failure.json";
import { useTranslation } from "@/components/Layout/TranslationContext";
import { useDispatch, useSelector } from "react-redux";
import { clearCart, clearChekoutData } from "@/redux/reducers/cartSlice";
import { clearReorder } from "@/redux/reducers/reorderSlice";

const PaymentStatus = () => {
  const t = useTranslation();
  const router = useRouter();
  const dispatch = useDispatch();
  const { status, payment_status, order_id, st } = router.query;
  const isReorderMode = useSelector(state => state.reorder.isReOrder);
  
  // Helper function to normalize and check payment status
  // Handles arrays (when duplicate query params exist), case-insensitive matching, and multiple success indicators
  const normalizeStatus = (value) => {
    if (!value) return null;
    // Handle array case (duplicate query parameters)
    const normalized = Array.isArray(value) ? value[value.length - 1] : value;
    return String(normalized).toLowerCase().trim();
  };
  
  // Check multiple success indicators from PayPal and other payment gateways
  // PayPal sends: payment_status=success, payment_status=Completed, st=Completed
  const normalizedStatus = normalizeStatus(status);
  const normalizedPaymentStatus = normalizeStatus(payment_status);
  const normalizedSt = normalizeStatus(st);
  
  // Success conditions: check all possible success indicators
  const isSuccess = 
    normalizedStatus === "successful" || 
    normalizedStatus === "success" ||
    normalizedPaymentStatus === 'completed' || 
    normalizedPaymentStatus === 'success' ||
    normalizedSt === 'completed' ||
    normalizedSt === 'success';
    
  // Clear any pending redirect-based payment flag left on Checkout page
  useEffect(() => {
    try {
      localStorage.removeItem('Servio_pending_payment');
    } catch (_) {}
  }, []);
  
  // Handle back button press - redirect to home only on success
  useEffect(() => {
    if (isSuccess) {
      window.history.pushState({ paymentStatusPage: true }, '');
      
      const handleBackButton = () => {
        router.push('/');
        dispatch(clearChekoutData());
        if (isReorderMode) {
          dispatch(clearReorder());
        } else {
          dispatch(clearCart());
        }
      };
      
      window.addEventListener('popstate', handleBackButton);
      
      return () => {
        window.removeEventListener('popstate', handleBackButton);
      };
    }
  }, [dispatch, isReorderMode, router, isSuccess]);
  
  // Handle navigation to home - only clear data on success
  const handleGoHome = () => {
    router.push("/");
    if (isSuccess) {
      dispatch(clearChekoutData());
      if (isReorderMode) {
        dispatch(clearReorder());
      } else {
        dispatch(clearCart());
      }
    }
  };

  // Handle navigation to order details - only clear data on success
  const handleGoToOrderDetails = () => {
    if (order_id) {
      if (isSuccess) {
        dispatch(clearChekoutData());
        if (isReorderMode) {
          dispatch(clearReorder());
        } else {
          dispatch(clearCart());
        }
      }
      router.push(`/booking/inv-${order_id}`);
    }
  };

  // Handle re-payment attempt
  const handleRetryPayment = () => {
    router.push(`/checkout?isRepayment=1`);
  };

  return (
    <div className="flex items-center justify-center min-h-screen light_bg_color">
      <div className="card_bg p-8 rounded-lg shadow-lg text-center max-w-md w-full">
        <div className="flex justify-center mb-6">
          <Lottie
            animationData={isSuccess ? successAnimation : failedAnimation}
            loop={false}
            style={{ width: 250, height: 250 }}
          />
        </div>

        <h1 className="text-2xl font-bold mb-4">
          {isSuccess ? t("paymentSuccess") : t("paymentFailed")}
        </h1>
        
        <p className="description_color mb-8">
          {isSuccess ? t("paymentSuccessText") : t("paymentFailedText")}
        </p>

        <div className="flex flex-col space-y-4">
          {/* Show retry payment button only on failure */}
          {!isSuccess && (
            <button
              onClick={handleRetryPayment}
              className="w-full primary_bg_color p-3 rounded-lg text-white"
            >
              {t("retryPayment")}
            </button>
          )}

          <button
            onClick={handleGoToOrderDetails}
            className="w-full primary_bg_color p-3 rounded-lg text-white"
          >
            {t("viewBookingDetails")}
          </button>
          
          <button
            onClick={handleGoHome}
            className="w-full light_bg_color p-3 rounded-lg primary_text_color"
          >
            {t("goHome")}
          </button>
        </div>
      </div>
    </div>
  );
};

export default PaymentStatus;
