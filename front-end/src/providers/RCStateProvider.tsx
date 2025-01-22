import React, { createContext, useContext, useReducer, useEffect } from 'react';
import { useChannel } from '../hooks/useChannel';
import { useCamera } from '../hooks/useCamera';
import { useAudio } from '../hooks/useAudio';
import { useGamepad } from '../hooks/useGamepad';
import { useConnection } from '../hooks/useConnection';

type RCState = {
  isConnected: boolean;
  channelStatus: any;
  cameraStatus: any;
  audioStatus: any;
  gamepadStatus: any;
};

type RCAction = {
  type: string;
  payload: any;
};

const initialState: RCState = {
  isConnected: false,
  channelStatus: null,
  cameraStatus: null,
  audioStatus: null,
  gamepadStatus: null,
};

const RCStateContext = createContext<{
  state: RCState;
  dispatch: React.Dispatch<RCAction>;
}>({ state: initialState, dispatch: () => null });

const rcReducer = (state: RCState, action: RCAction): RCState => {
  switch (action.type) {
    case 'SET_CONNECTION_STATUS':
      return { ...state, isConnected: action.payload };
    case 'UPDATE_CHANNEL_STATUS':
      return { ...state, channelStatus: action.payload };
    case 'UPDATE_CAMERA_STATUS':
      return { ...state, cameraStatus: action.payload };
    case 'UPDATE_AUDIO_STATUS':
      return { ...state, audioStatus: action.payload };
    case 'UPDATE_GAMEPAD_STATUS':
      return { ...state, gamepadStatus: action.payload };
    default:
      return state;
  }
};

export const RCStateProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [state, dispatch] = useReducer(rcReducer, initialState);
  const { status: channelStatus } = useChannel();
  const { status: cameraStatus } = useCamera();
  const { status: audioStatus } = useAudio();
  const { status: gamepadStatus } = useGamepad();
  const { isConnected } = useConnection();

  useEffect(() => {
    dispatch({ type: 'SET_CONNECTION_STATUS', payload: isConnected });
  }, [isConnected]);

  useEffect(() => {
    dispatch({ type: 'UPDATE_CHANNEL_STATUS', payload: channelStatus });
  }, [channelStatus]);

  useEffect(() => {
    dispatch({ type: 'UPDATE_CAMERA_STATUS', payload: cameraStatus });
  }, [cameraStatus]);

  useEffect(() => {
    dispatch({ type: 'UPDATE_AUDIO_STATUS', payload: audioStatus });
  }, [audioStatus]);

  useEffect(() => {
    dispatch({ type: 'UPDATE_GAMEPAD_STATUS', payload: gamepadStatus });
  }, [gamepadStatus]);

  return (
    <RCStateContext.Provider value={{ state, dispatch }}>
      {children}
    </RCStateContext.Provider>
  );
};

export const useRCState = () => {
  const context = useContext(RCStateContext);
  if (!context) {
    throw new Error('useRCState must be used within an RCStateProvider');
  }
  return context;
};