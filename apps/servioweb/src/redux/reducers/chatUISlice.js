import { createSlice } from '@reduxjs/toolkit';

const initialState = {
  chatStep: 'list',
  selectedChatId: null, // Will store uniqueId: provider_id_bookingId or provider_id_pre
  selectedChat: null,
  chatType: null, // 'pre' or 'post' or 'admin'
  isAdmin: false, // Track if we're in admin chat
  lastChatId: null, // Track the last chat viewed for comparison
};

const chatUISlice = createSlice({
  name: 'chatUI',
  initialState,
  reducers: {
    setChatStep: (state, action) => {
      state.chatStep = action.payload;
    },
    setSelectedChatId: (state, action) => {
      // Store the previous chat ID before changing
      state.lastChatId = state.selectedChatId;
      state.selectedChatId = action.payload;
      
      // Update chatType based on the unique identifier
      if (action.payload) {
        state.chatType = action.payload.includes('_pre') ? 'pre' : 'post';
      } else if (state.isAdmin) {
        state.chatType = 'admin';
      } else {
        state.chatType = null;
      }
    },
    setSelectedChat: (state, action) => {
      state.selectedChat = action.payload;
      
      // Update chatType based on booking_id
      if (action.payload) {
        state.chatType = action.payload.booking_id ? 'post' : 'pre';
      } else if (state.isAdmin) {
        state.chatType = 'admin';
      } else {
        state.chatType = null;
      }
    },
    setIsAdmin: (state, action) => {
      // Store the last state before changing
      state.lastChatId = state.selectedChatId;
      
      state.isAdmin = action.payload;
      
      // Update chatType based on new admin state
      if (action.payload) {
        state.chatType = 'admin';
      } else if (state.selectedChat) {
        state.chatType = state.selectedChat.booking_id ? 'post' : 'pre';
      } else {
        state.chatType = null;
      }
    },
    resetChatUI: (state) => {
      // Clear everything
      state.chatStep = 'list';
      state.selectedChatId = null;
      state.selectedChat = null;
      state.chatType = null;
      state.isAdmin = false;
      state.lastChatId = null;
    },
  },
});

// Export actions
export const { setChatStep, setSelectedChatId, setSelectedChat, setIsAdmin, resetChatUI } = chatUISlice.actions;

// Export selectors
export const selectChatUI = (state) => state.chatUI;
export const selectChatStep = (state) => state.chatUI.chatStep;
export const selectSelectedChatId = (state) => state.chatUI.selectedChatId;
export const selectSelectedChat = (state) => state.chatUI.selectedChat;
export const selectChatType = (state) => state.chatUI.chatType;
export const selectIsAdmin = (state) => state.chatUI.isAdmin;
export const selectLastChatId = (state) => state.chatUI.lastChatId;

export default chatUISlice.reducer; 