import React from 'react';
import { Box, Container, Grid, Paper, Typography, Tab, Tabs } from '@mui/material';
import { styled } from '@mui/material/styles';
import BasicSettings from './BasicSettings';
import ChannelSettings from './ChannelSettings';
import CameraSettings from './CameraSettings';
import SoundSettings from './SoundSettings';
import UISettings from './UISettings';

const SettingsContainer = styled(Container)(({ theme }) => ({
  padding: theme.spacing(3),
  [theme.breakpoints.down('sm')]: {
    padding: theme.spacing(2),
  },
}));

const SettingsPanel = styled(Paper)(({ theme }) => ({
  padding: theme.spacing(3),
  borderRadius: theme.shape.borderRadius,
  boxShadow: theme.shadows[1],
}));

const Settings = () => {
  const [currentTab, setCurrentTab] = React.useState(0);

  const handleTabChange = (event, newValue) => {
    setCurrentTab(newValue);
  };

  const renderTabContent = () => {
    switch (currentTab) {
      case 0:
        return <BasicSettings />;
      case 1:
        return <ChannelSettings />;
      case 2:
        return <CameraSettings />;
      case 3:
        return <SoundSettings />;
      case 4:
        return <UISettings />;
      default:
        return null;
    }
  };

  return (
    <SettingsContainer maxWidth="lg">
      <Grid container spacing={3}>
        <Grid item xs={12}>
          <Typography variant="h4" gutterBottom>
            设置
          </Typography>
        </Grid>
        <Grid item xs={12}>
          <SettingsPanel>
            <Box sx={{ borderBottom: 1, borderColor: 'divider', mb: 3 }}>
              <Tabs
                value={currentTab}
                onChange={handleTabChange}
                variant="scrollable"
                scrollButtons="auto"
                aria-label="settings tabs"
              >
                <Tab label="基本设置" />
                <Tab label="通道设置" />
                <Tab label="摄像头设置" />
                <Tab label="音频设置" />
                <Tab label="界面设置" />
              </Tabs>
            </Box>
            {renderTabContent()}
          </SettingsPanel>
        </Grid>
      </Grid>
    </SettingsContainer>
  );
};

export default Settings;