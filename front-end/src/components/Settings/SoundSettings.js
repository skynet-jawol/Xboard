import React from 'react';
import { Box, Grid, TextField, Button, FormControlLabel, Switch, Typography, Slider } from '@mui/material';
import { useFormik } from 'formik';
import * as yup from 'yup';

const validationSchema = yup.object({
  volume: yup
    .number()
    .required('音量不能为空')
    .min(0, '音量必须大于等于0')
    .max(100, '音量不能超过100'),
  enableMicrophone: yup.boolean(),
  enableTTS: yup.boolean(),
  ttsVolume: yup
    .number()
    .required('TTS音量不能为空')
    .min(0, '音量必须大于等于0')
    .max(100, '音量不能超过100'),
});

const SoundSettings = () => {
  const formik = useFormik({
    initialValues: {
      volume: 80,
      enableMicrophone: false,
      enableTTS: false,
      ttsVolume: 80,
    },
    validationSchema: validationSchema,
    onSubmit: (values) => {
      // TODO: 实现保存设置的逻辑
      console.log('Form values:', values);
    },
  });

  return (
    <Box component="form" onSubmit={formik.handleSubmit} noValidate>
      <Grid container spacing={3}>
        <Grid item xs={12}>
          <Typography variant="h6" gutterBottom>
            音频配置
          </Typography>
        </Grid>
        <Grid item xs={12}>
          <Typography gutterBottom>系统音量</Typography>
          <Slider
            value={formik.values.volume}
            onChange={(_, value) => formik.setFieldValue('volume', value)}
            valueLabelDisplay="auto"
            step={1}
            marks
            min={0}
            max={100}
          />
        </Grid>
        <Grid item xs={12}>
          <FormControlLabel
            control={
              <Switch
                checked={formik.values.enableMicrophone}
                onChange={formik.handleChange}
                name="enableMicrophone"
              />
            }
            label="启用麦克风"
          />
        </Grid>
        <Grid item xs={12}>
          <FormControlLabel
            control={
              <Switch
                checked={formik.values.enableTTS}
                onChange={formik.handleChange}
                name="enableTTS"
              />
            }
            label="启用TTS语音"
          />
        </Grid>
        {formik.values.enableTTS && (
          <Grid item xs={12}>
            <Typography gutterBottom>TTS音量</Typography>
            <Slider
              value={formik.values.ttsVolume}
              onChange={(_, value) => formik.setFieldValue('ttsVolume', value)}
              valueLabelDisplay="auto"
              step={1}
              marks
              min={0}
              max={100}
            />
          </Grid>
        )}
        <Grid item xs={12}>
          <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 2 }}>
            <Button
              variant="outlined"
              onClick={() => formik.resetForm()}
            >
              重置
            </Button>
            <Button
              type="submit"
              variant="contained"
              color="primary"
            >
              保存
            </Button>
          </Box>
        </Grid>
      </Grid>
    </Box>
  );
};

export default SoundSettings;