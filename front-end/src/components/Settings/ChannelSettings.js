import React from 'react';
import { Box, Grid, TextField, Button, FormControlLabel, Switch, Typography } from '@mui/material';
import { useFormik } from 'formik';
import * as yup from 'yup';

const validationSchema = yup.object({
  channelCount: yup
    .number()
    .required('通道数量不能为空')
    .min(1, '通道数量必须大于0')
    .max(16, '通道数量不能超过16'),
  defaultValue: yup
    .number()
    .required('默认值不能为空')
    .min(0, '默认值必须大于等于0')
    .max(100, '默认值不能超过100'),
  reverseControl: yup.boolean(),
});

const ChannelSettings = () => {
  const formik = useFormik({
    initialValues: {
      channelCount: 4,
      defaultValue: 50,
      reverseControl: false,
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
            通道配置
          </Typography>
        </Grid>
        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            id="channelCount"
            name="channelCount"
            label="通道数量"
            type="number"
            value={formik.values.channelCount}
            onChange={formik.handleChange}
            error={formik.touched.channelCount && Boolean(formik.errors.channelCount)}
            helperText={formik.touched.channelCount && formik.errors.channelCount}
          />
        </Grid>
        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            id="defaultValue"
            name="defaultValue"
            label="默认值"
            type="number"
            value={formik.values.defaultValue}
            onChange={formik.handleChange}
            error={formik.touched.defaultValue && Boolean(formik.errors.defaultValue)}
            helperText={formik.touched.defaultValue && formik.errors.defaultValue}
          />
        </Grid>
        <Grid item xs={12}>
          <FormControlLabel
            control={
              <Switch
                checked={formik.values.reverseControl}
                onChange={formik.handleChange}
                name="reverseControl"
              />
            }
            label="反向控制"
          />
        </Grid>
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

export default ChannelSettings;