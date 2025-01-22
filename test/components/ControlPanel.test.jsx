import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { ThemeProvider } from '@mui/material/styles';
import { ControlPanel } from '@/components/ControlPanel';
import { RCStateProvider } from '@/contexts/RCStateContext';

describe('ControlPanel 组件', () => {
  const renderWithProviders = (component) => {
    return render(
      <ThemeProvider theme={theme}>
        <RCStateProvider>
          {component}
        </RCStateProvider>
      </ThemeProvider>
    );
  };

  it('应该正确渲染控制面板', () => {
    renderWithProviders(<ControlPanel />);
    expect(screen.getByTestId('control-panel')).toBeInTheDocument();
  });

  it('应该响应手柄输入', () => {
    renderWithProviders(<ControlPanel />);
    const controlStick = screen.getByTestId('control-stick');
    fireEvent.mouseDown(controlStick);
    fireEvent.mouseMove(controlStick, { clientX: 100, clientY: 100 });
    expect(screen.getByTestId('control-value')).toHaveTextContent('100');
  });

  it('应该正确显示连接状态', () => {
    renderWithProviders(<ControlPanel />);
    expect(screen.getByTestId('connection-status')).toHaveTextContent('已连接');
  });
});