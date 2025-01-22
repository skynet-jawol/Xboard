import { useState, useEffect, useCallback } from 'react';
import { message } from 'antd';

interface ChannelState {
  value: number;
  min: number;
  max: number;
  reverse: boolean;
  trim: number;
  expo: number;
}

interface ChannelConfig {
  channelId: number;
  initialState?: Partial<ChannelState>;
}

const DEFAULT_STATE: ChannelState = {
  value: 1500,
  min: 1000,
  max: 2000,
  reverse: false,
  trim: 0,
  expo: 0
};

export const useChannel = ({ channelId, initialState = {} }: ChannelConfig) => {
  const [state, setState] = useState<ChannelState>({
    ...DEFAULT_STATE,
    ...initialState
  });

  // 持久化状态到localStorage
  useEffect(() => {
    const key = `channel-${channelId}`;
    const savedState = localStorage.getItem(key);
    if (savedState) {
      try {
        setState(JSON.parse(savedState));
      } catch (e) {
        console.error('Failed to load channel state:', e);
      }
    }
  }, [channelId]);

  // 保存状态变更
  useEffect(() => {
    const key = `channel-${channelId}`;
    localStorage.setItem(key, JSON.stringify(state));
  }, [state, channelId]);

  // 更新通道值
  const updateValue = useCallback((newValue: number) => {
    setState(prev => {
      const value = Math.min(Math.max(newValue, prev.min), prev.max);
      return { ...prev, value };
    });
  }, []);

  // 更新通道配置
  const updateConfig = useCallback((config: Partial<ChannelState>) => {
    setState(prev => ({ ...prev, ...config }));
    message.success(`通道 ${channelId} 配置已更新`);
  }, [channelId]);

  // 重置通道状态
  const resetChannel = useCallback(() => {
    setState(DEFAULT_STATE);
    message.info(`通道 ${channelId} 已重置`);
  }, [channelId]);

  // 计算实际输出值（考虑反向、微调和指数）
  const computeOutput = useCallback(() => {
    let output = state.value;
    
    // 应用微调
    output += state.trim;
    
    // 应用反向
    if (state.reverse) {
      output = state.max - (output - state.min);
    }
    
    // 应用指数曲线
    if (state.expo !== 0) {
      const center = (state.max + state.min) / 2;
      const range = (state.max - state.min) / 2;
      const normalizedValue = (output - center) / range;
      const expoValue = Math.sign(normalizedValue) * Math.pow(Math.abs(normalizedValue), 1 + state.expo);
      output = center + range * expoValue;
    }
    
    return Math.round(output);
  }, [state]);

  return {
    state,
    updateValue,
    updateConfig,
    resetChannel,
    computeOutput
  };
};