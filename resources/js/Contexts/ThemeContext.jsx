import { createContext, useContext, useState, useEffect, useCallback } from 'react';

const ThemeContext = createContext(null);

const STORAGE_KEY = 'cm-high-contrast';

export function ThemeProvider({ children }) {
    const [highContrast, setHighContrast] = useState(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem(STORAGE_KEY) === '1';
    });

    useEffect(() => {
        const cl = document.documentElement.classList;
        if (highContrast) {
            cl.add('high-contrast');
        } else {
            cl.remove('high-contrast');
        }
        localStorage.setItem(STORAGE_KEY, highContrast ? '1' : '0');

        // Update meta theme-color for mobile browsers
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = highContrast ? '#020302' : '#3D7A32';
    }, [highContrast]);

    const toggleHighContrast = useCallback(() => {
        setHighContrast((v) => !v);
    }, []);

    return (
        <ThemeContext.Provider value={{ highContrast, toggleHighContrast }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const context = useContext(ThemeContext);
    if (!context) {
        throw new Error('useTheme must be used within a ThemeProvider');
    }
    return context;
}
