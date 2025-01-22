import React, { createContext, useContext, useMemo } from 'react';
import { ThemeProvider as MuiThemeProvider, createTheme } from '@mui/material/styles';
import { useConfig } from './ConfigProvider';

const lightTheme = createTheme({
  palette: {
    mode: 'light',
    primary: {
      main: '#1976d2',
    },
    secondary: {
      main: '#dc004e',
    },
  },
});

const darkTheme = createTheme({
  palette: {
    mode: 'dark',
    primary: {
      main: '#90caf9',
    },
    secondary: {
      main: '#f48fb1',
    },
  },
});

const ThemeContext = createContext<{
  toggleTheme: () => void;
}>({ toggleTheme: () => null });

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { state: configState, dispatch } = useConfig();

  const toggleTheme = () => {
    dispatch({
      type: 'SET_THEME',
      payload: configState.theme === 'light' ? 'dark' : 'light',
    });
  };

  const theme = useMemo(
    () => (configState.theme === 'light' ? lightTheme : darkTheme),
    [configState.theme]
  );

  return (
    <ThemeContext.Provider value={{ toggleTheme }}>
      <MuiThemeProvider theme={theme}>{children}</MuiThemeProvider>
    </ThemeContext.Provider>
  );
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
};