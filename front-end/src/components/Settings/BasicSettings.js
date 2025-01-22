import React from 'react';
import { Box, Grid, TextField, Button, Alert } from '@mui/material';
import { useFormik } from 'formik';
import * as yup from 'yup';

const validationSchema = yup.object({
  serverAddress: yup
    .string()
    .required('服务器地址不能为空')
    .matches(/^(http|https):\/\/[^\s]*$/, '请输入有效的服务器地址'),
  apiPort: yup
    .number()
    .required('API端口不能为空')
    .min(1, '端口号必须大于0')
    .max(65535, '端口号必须小于65536'),
  streamPort: yup
    .number()
    .required('流媒体端口不能为空')
    .min(1, '端口号必须大于0')
    .max(65535, '端口号必须小于65536'),
});

const BasicSettings = () => {
  const formik = useFormik({
    initialValues: {
      serverAddress: '',
      apiPort: '',
      streamPort: '',
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
        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            id="serverAddress"
            name="serverAddress"
            label="服务器地址"
            value={formik.values.serverAddress}
            onChange={formik.handleChange}
            error={formik.touched.serverAddress && Boolean(formik.errors.serverAddress)}
            helperText={formik.touched.serverAddress && formik.errors.serverAddress}
          />
        </Grid>
        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            id="apiPort"
            name="apiPort"
            label="API端口"
            type="number"
            value={formik.values.apiPort}
            onChange={formik.handleChange}
            error={formik.touched.apiPort && Boolean(formik.errors.apiPort)}
            helperText={formik.touched.apiPort && formik.errors.apiPort}
          />
        </Grid>
        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            id="streamPort"
            name="streamPort"
            label="流媒体端口"
            type="number"
            value={formik.values.streamPort}
            onChange={formik.handleChange}
            error={formik.touched.streamPort && Boolean(formik.errors.streamPort)}
            helperText={formik.touched.streamPort && formik.errors.streamPort}
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

export default BasicSettings;