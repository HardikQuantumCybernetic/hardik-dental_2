import { createRoot } from 'react-dom/client'
import App from './App.tsx'
import './index.css'

// Optional Clerk integration - only wrap with ClerkProvider if key is available
const PUBLISHABLE_KEY = import.meta.env.VITE_CLERK_PUBLISHABLE_KEY;

// Render app with or without Clerk based on configuration
const renderApp = async () => {
  const root = createRoot(document.getElementById("root")!);
  
  if (PUBLISHABLE_KEY && PUBLISHABLE_KEY.trim() !== '' && PUBLISHABLE_KEY !== ' ') {
    // Dynamically import Clerk only when key is available
    const { ClerkProvider } = await import('@clerk/clerk-react');
    root.render(
      <ClerkProvider publishableKey={PUBLISHABLE_KEY}>
        <App />
      </ClerkProvider>
    );
  } else {
    // Run without Clerk for VPS/PHP backend deployment
    console.log('Running without Clerk authentication (VPS mode)');
    root.render(<App />);
  }
};

renderApp();
