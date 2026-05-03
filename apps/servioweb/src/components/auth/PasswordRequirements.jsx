"use client";
import React from "react";
import { motion } from "framer-motion";

/**
 * PasswordRequirements Component
 * Displays real-time password validation feedback with animated checkmarks
 *
 * @param {string} password - The password to validate
 * @param {function} t - Translation function
 */
const PasswordRequirements = ({ password = "", t }) => {
  // Password validation rules
  const requirements = [
    {
      key: "minLength",
      label: t?.("passwordMustBeAtLeast8Chars") || "At least 8 characters",
      test: (pwd) => pwd.length >= 8,
    },
    {
      key: "uppercase",
      label:
        t?.("passwordMustHaveUppercase") || "At least one uppercase letter",
      test: (pwd) => /[A-Z]/.test(pwd),
    },
    {
      key: "lowercase",
      label:
        t?.("passwordMustHaveLowercase") || "At least one lowercase letter",
      test: (pwd) => /[a-z]/.test(pwd),
    },
    {
      key: "number",
      label: t?.("passwordMustHaveNumber") || "At least one number",
      test: (pwd) => /[0-9]/.test(pwd),
    },
    {
      key: "special",
      label:
        t?.("passwordMustHaveSpecialChar") || "At least one special character",
      test: (pwd) => /[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\;'/`~]/.test(pwd),
    },
  ];

  return (
    <motion.div
      initial={{ opacity: 0, height: 0 }}
      animate={{ opacity: 1, height: "auto" }}
      exit={{ opacity: 0, height: 0 }}
      transition={{ duration: 0.2 }}
      className="mt-2 space-y-1"
    >
      {requirements.map((req) => {
        const isMet = req.test(password);
        return (
          <motion.div
            key={req.key}
            initial={{ x: -10, opacity: 0 }}
            animate={{ x: 0, opacity: 1 }}
            transition={{ duration: 0.2 }}
            className="flex items-center gap-2"
          >
            <motion.div
              initial={{ scale: 0.8 }}
              animate={{
                scale: isMet ? 1 : 0.8,
                backgroundColor: isMet ? "#22c55e" : "transparent",
                borderColor: isMet ? "#22c55e" : "#9ca3af",
              }}
              transition={{ duration: 0.2 }}
              className={`w-4 h-4 rounded-full border-2 flex items-center justify-center`}
            >
              {isMet && (
                <motion.svg
                  initial={{ scale: 0, opacity: 0 }}
                  animate={{ scale: 1, opacity: 1 }}
                  transition={{ duration: 0.2, delay: 0.1 }}
                  className="w-2.5 h-2.5 text-white"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={3}
                    d="M5 13l4 4L19 7"
                  />
                </motion.svg>
              )}
            </motion.div>
            <motion.span
              animate={{
                color: isMet ? "#22c55e" : "#6b7280",
              }}
              transition={{ duration: 0.2 }}
              className="text-xs"
            >
              {req.label}
            </motion.span>
          </motion.div>
        );
      })}
    </motion.div>
  );
};

/**
 * Validates password against all requirements
 * @param {string} password - Password to validate
 * @returns {boolean} - True if all requirements are met
 */
export const validatePasswordStrength = (password) => {
  if (!password) return false;
  return (
    password.length >= 8 &&
    /[A-Z]/.test(password) &&
    /[a-z]/.test(password) &&
    /[0-9]/.test(password) &&
    /[!@#$%^&*(),.?":{}|<>_\-+=\[\]\\;'/`~]/.test(password)
  );
};

export default PasswordRequirements;
