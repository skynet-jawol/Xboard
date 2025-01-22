import React, { createContext, useContext, useReducer, useEffect } from 'react';

type Device = {
  id: string;
  name: string;
  type: 'camera' | 'audio' | 'gamepad' | 'other';
  status: 'connected' | 'disconnected' | 'error';
  lastConnected?: Date;
  config?: Record<string, any>;
};

type DeviceState = {
  devices: Device[];
  activeDevices: Record<string, Device>;
  isScanning: boolean;
  error: string | null;
};

type DeviceAction = {
  type: string;
  payload: any;
};

const initialState: DeviceState = {
  devices: [],
  activeDevices: {},
  isScanning: false,
  error: null,
};

const DeviceContext = createContext<{
  state: DeviceState;
  dispatch: React.Dispatch<DeviceAction>;
}>({ state: initialState, dispatch: () => null });

const deviceReducer = (state: DeviceState, action: DeviceAction): DeviceState => {
  switch (action.type) {
    case 'ADD_DEVICE':
      return {
        ...state,
        devices: [...state.devices, action.payload],
      };
    case 'REMOVE_DEVICE':
      return {
        ...state,
        devices: state.devices.filter(device => device.id !== action.payload),
        activeDevices: Object.fromEntries(
          Object.entries(state.activeDevices).filter(([id]) => id !== action.payload)
        ),
      };
    case 'UPDATE_DEVICE_STATUS':
      return {
        ...state,
        devices: state.devices.map(device =>
          device.id === action.payload.id
            ? { ...device, status: action.payload.status }
            : device
        ),
      };
    case 'SET_ACTIVE_DEVICE':
      return {
        ...state,
        activeDevices: {
          ...state.activeDevices,
          [action.payload.type]: state.devices.find(d => d.id === action.payload.id),
        },
      };
    case 'SET_SCANNING':
      return { ...state, isScanning: action.payload };
    case 'SET_ERROR':
      return { ...state, error: action.payload };
    default:
      return state;
  }
};

export const DeviceProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [state, dispatch] = useReducer(deviceReducer, initialState);

  // 监听设备变化
  useEffect(() => {
    const handleDeviceChange = (event: any) => {
      if (event.device) {
        dispatch({
          type: event.type === 'connected' ? 'ADD_DEVICE' : 'REMOVE_DEVICE',
          payload: event.type === 'connected' ? event.device : event.device.id,
        });
      }
    };

    // 这里可以添加设备监听逻辑
    window.addEventListener('devicechange', handleDeviceChange);

    return () => {
      window.removeEventListener('devicechange', handleDeviceChange);
    };
  }, []);

  return (
    <DeviceContext.Provider value={{ state, dispatch }}>
      {children}
    </DeviceContext.Provider>
  );
};

export const useDevice = () => {
  const context = useContext(DeviceContext);
  if (!context) {
    throw new Error('useDevice must be used within a DeviceProvider');
  }
  return context;
};