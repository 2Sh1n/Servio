importScripts('https://www.gstatic.com/firebasejs/9.6.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.6.1/firebase-messaging-compat.js');

firebase.initializeApp({
  apiKey: "AIzaSyBg7kmFnXiBxvxg3l5bYU-2eNnoKaacDfA",
  authDomain: "servio-565f8.firebaseapp.com",
  projectId: "servio-565f8",
  storageBucket: "servio-565f8.firebasestorage.app",
  messagingSenderId: "587034022459",
  appId: "1:587034022459:web:2fb7a2967efa6b6dc0821e"
});

const messaging = firebase.messaging();
