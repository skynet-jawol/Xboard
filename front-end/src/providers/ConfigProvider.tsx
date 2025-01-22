import React, { createContext, useContext, useReducer, useEffect } from 'react';

type ConfigState = {
  theme: 'light' | 'dark';
  language: string;
  controlMode: 'keyboard' | 'gamepad' | 'touch';
  videoQuality: 'low' | 'medium' | 'high';
  audioEnabled: boolean;
  cameraEnabled: boolean;
};

type ConfigAction = {
  type: string;
  payload: any;
};

const initialState: ConfigState = {
  theme: 'light',
  language: 'zh-CN',
  controlMode: 'keyboard',
  videoQuality: 'medium',
  audioEnabled: true,
  cameraEnabled: true,
};

const ConfigContext = createContext<{
  state: ConfigState;
  dispatch: React.Dispatch<ConfigAction>;
}>({ state: initialState, dispatch: () => null });

const configReducer = (state: ConfigState, action: ConfigAction): ConfigState => {
  switch (action.type) {
    case 'SET_THEME':
      return { ...state, theme: action.payload };
    case 'SET_LANGUAGE':
      return { ...state, language: action.payload };
    case 'SET_CONTROL_MODE':
      return { ...state, controlMode: action.payload };
    case 'SET_VIDEO_QUALITY':
      return { ...state, videoQuality: action.payload };
    case 'SET_AUDIO_ENABLED':
      return { ...state, audioEnabled: action.payload };
    case 'SET_CAMERA_ENABLED':
      return { ...state, cameraEnabled: action.payload };
    default:
      return state;
  }
};

export const ConfigProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [state, dispatch] = useReducer(configReducer, initialState);

  // 从localStorage加载配置
  useEffect(() => {
    const savedConfig = localStorage.getItem('rc-config');
    if (savedConfig) {
      try {
        const parsedConfig = JSON.parse(savedConfig);
        Object.entries(parsedConfig).forEach(([key, value]) => {
          dispatch({ type: `SET_${key.toUpperCase()}`, payload: value });
        });
      } catch (error) {
        console.error('Failed to load config:', error);
      }
    }
  }, []);

  // 保存配置到localStorage
  useEffect(() => {
    localStorage.setItem('rc-config', JSON.stringify(state));
  }, [state]);

  return (
    <ConfigContext.Provider value={{ state, dispatch }}>
      {children}
    </ConfigContext.Provider>
  );
};

export const useConfig = () => {
  const context = useContext(ConfigContext);
  if (!context) {
    throw new Error('useConfig must be used within a ConfigProvider');
  }
  return context;
};