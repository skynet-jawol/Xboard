import React from 'react';
import { Box, Grid, TextField, Button, FormControlLabel, Switch, Typography, Select, MenuItem, FormControl, InputLabel } from '@mui/material';
import { useFormik } from 'formik';
import * as yup from 'yup';

const validationSchema = yup.object({
  resolution: yup.string().required('分辨率不能为空'),
  frameRate: yup
    .number()
    .required('帧率不能为空')
    .min(1, '帧率必须大于0')
    .max(60, '帧率不能超过60'),
  quality: yup
    .number()
    .required('画质不能为空')
    .min(1, '画质必须大于0')
    .max(100, '画质不能超过100'),
  flipHorizontal: yup.boolean(),
  flipVertical: yup.boolean(),
});

const CameraSettings = () => {
  const formik = useFormik({
    initialValues: {
      resolution: '1280x720',
      frameRate: 30,
      quality: 80,
      flipHorizontal: false,
      flipVertical: false,
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
            摄像头配置
          </Typography>
        </Grid>
        <Grid item xs={12} md={6}>
          <FormControl fullWidth>
            <InputLabel id="resolution-label">分辨率</InputLabel>
            <Select
              labelId="resolution-label"
              id="resolution"
              name="resolution"
              value={formik.values.resolution}
              onChange={formik.handleChange}
              label="分辨率"
            >
              <MenuItem value="640x480">640x480</MenuItem>
              <MenuItem value="1280x720">1280x720</MenuItem>
              <MenuItem value="1920x1080">1920x1080</MenuItem>
            </Select>
          </FormControl>
        </Grid>
        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            id="frameRate"
            name="frameRate"
            label="帧率"
            type="number"
            value={formik.values.frameRate}
            onChange={formik.handleChange}
            error={formik.touched.frameRate && Boolean(formik.errors.frameRate)}
            helperText={formik.touched.frameRate && formik.errors.frameRate}
          />
        </Grid>
        <Grid item xs={12} md={6}>
          <TextField
            fullWidth
            id="quality"
            name="quality"
            label="画质"
            type="number"
            value={formik.values.quality}
            onChange={formik.handleChange}
            error={formik.touched.quality && Boolean(formik.errors.quality)}
            helperText={formik.touched.quality && formik.errors.quality}
          />
        </Grid>
        <Grid item xs={12}>
          <FormControlLabel
            control={
              <Switch
                checked={formik.values.flipHorizontal}
                onChange={formik.handleChange}
                name="flipHorizontal"
              />
            }
            label="水平翻转"
          />
        </Grid>
        <Grid item xs={12}>
          <FormControlLabel
            control={
              <Switch
                checked={formik.values.flipVertical}
                onChange={formik.handleChange}
                name="flipVertical"
              />
            }
            label="垂直翻转"
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

export default CameraSettings;