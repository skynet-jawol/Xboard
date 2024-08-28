#include <nan.h>
#include "pwm.h"



namespace {

using v8::Context;
using v8::Function;
using v8::FunctionTemplate;
using v8::FunctionCallbackInfo;
using v8::Value;
using v8::Local;

void Method(const FunctionCallbackInfo<Value>& info) {
  info.GetReturnValue().Set(Nan::New("world").ToLocalChecked());
}

// pwm.setup(1); // 1 = resolution 1us
// void Setup(const FunctionCallbackInfo<v8::Value>& info) {
//   if (info.Length() < 1) {
//     Nan::ThrowTypeError("Wrong number of arguments, expected 1");
//     return;
//   }

//   if (!info[0]->IsNumber()) {
//     Nan::ThrowTypeError("Wrong arguments");
//     return;
//   }

//   double arg0 = info[0]->NumberValue();
//   int incrementInUs = (int)arg0;

//   setup(incrementInUs, DELAY_VIA_PWM);
// }

// pwm.init_channel(14, 3000); // 14=DMA channel 14;  3000=full cycle time is 3000us
void PwmChannelInit(const FunctionCallbackInfo<v8::Value>& info) {
  if (info.Length() < 5) {
    Nan::ThrowTypeError("Wrong number of arguments, expected 5");
    return;
  }

  if (!info[0]->IsNumber() || !info[1]->IsNumber()) {
    Nan::ThrowTypeError("Wrong arguments");
    return;
  }

  double arg0 = info[0].As<v8::Number>()->Value();
  double arg1 = info[1].As<v8::Number>()->Value();
  double arg2 = info[2].As<v8::Number>()->Value();
  double arg3 = info[3].As<v8::Number>()->Value();
  double arg4 = info[4].As<v8::Number>()->Value();
  int dma_channel = (int)arg0;
  int cycle_time_us = (int)arg1;
  int step_time_us = (int)arg2;
  int delay_hw = (int)arg3;
  int invert = (int)arg4;

  pwm_channel_init(dma_channel, cycle_time_us, step_time_us, delay_hw, invert);
}

// pwm.clear_channel(14); // DMA channel 14
void PwmChannelShutdown(const FunctionCallbackInfo<v8::Value>& info) {
  if (info.Length() < 1) {
    Nan::ThrowTypeError("Wrong number of arguments, expected 1");
    return;
  }

  if (!info[0]->IsNumber()) {
    Nan::ThrowTypeError("Wrong arguments");
    return;
  }

  double arg0 = info[0].As<v8::Number>()->Value();
  int dma_channel = (int)arg0;

  pwm_channel_shutdown(dma_channel);
}

// pwm.add_channel_pulse(14, 17, 0, 50); // DMA channel 14; GPIO 17; start at 0us, width 50us
void PwmGpioAdd(const FunctionCallbackInfo<v8::Value>& info) {
  if (info.Length() < 3) {
    Nan::ThrowTypeError("Wrong number of arguments, expected 3");
    return;
  }

  if (!info[0]->IsNumber() || !info[1]->IsNumber() || !info[2]->IsNumber()) {
    Nan::ThrowTypeError("Wrong arguments");
    return;
  }

  double arg0 = info[0].As<v8::Number>()->Value();
  int dma_channel = (int)arg0; // 14 = DMA channel 14
  double arg1 = info[1].As<v8::Number>()->Value();
  int gpio_port = (int)arg1;  // 17 = GPIO 17
  double arg2 = info[2].As<v8::Number>()->Value();
  int width = (int)arg2; // 100 = 1000 us (assume resolution is 10us)

  pwm_gpio_add(dma_channel, gpio_port, width);
}


void PwmGpioSetWidth(const FunctionCallbackInfo<v8::Value>& info) {
  if (info.Length() < 2) {
    Nan::ThrowTypeError("Wrong number of arguments, expected 2");
    return;
  }

  if (!info[0]->IsNumber() || !info[1]->IsNumber()) {
    Nan::ThrowTypeError("Wrong arguments");
    return;
  }

  double arg0 = info[0].As<v8::Number>()->Value();
  double arg1 = info[1].As<v8::Number>()->Value();
  int gpio_port = (int)arg0;
  int width = (int)arg1;

  pwm_gpio_set_width(gpio_port, width);
}

void PwmGpioRelease(const FunctionCallbackInfo<v8::Value>& info) {
  if (info.Length() < 1) {
    Nan::ThrowTypeError("Wrong number of arguments, expected 1");
    return;
  }

  if (!info[0]->IsNumber()) {
    Nan::ThrowTypeError("Wrong arguments");
    return;
  }

  double arg0 = info[0].As<v8::Number>()->Value();
  int gpio_port = (int)arg0;

  pwm_gpio_release(gpio_port);
}

void PwmHostIsPi4(const FunctionCallbackInfo<v8::Value>& info) {
  auto is_pi4 = pwm_host_is_model_pi4();
  info.GetReturnValue().Set(Nan::New(is_pi4));
}

void PwmSetLogLevel(const FunctionCallbackInfo<v8::Value>& info) {
  if (info.Length() < 1) {
    Nan::ThrowTypeError("Wrong number of arguments, expected 1");
    return;
  }

  if (!info[0]->IsNumber()) {
    Nan::ThrowTypeError("Wrong arguments");
    return;
  }

  double arg0 = info[0].As<v8::Number>()->Value();
  int logLevel = (int)arg0;
  pwm_set_log_level(logLevel);
}

void Init(v8::Local<v8::Object> exports) {

  NODE_SET_METHOD(exports, "hello", Method);
  NODE_SET_METHOD(exports, "init_channel", PwmChannelInit);
  NODE_SET_METHOD(exports, "shutdown_channel", PwmChannelShutdown);
  NODE_SET_METHOD(exports, "add_gpio", PwmGpioAdd);
  NODE_SET_METHOD(exports, "set_width", PwmGpioSetWidth);
  NODE_SET_METHOD(exports, "release_gpio", PwmGpioRelease);
  NODE_SET_METHOD(exports, "host_is_model_pi4", PwmHostIsPi4);
  NODE_SET_METHOD(exports, "set_log_level", PwmSetLogLevel);

  // exports->Set(Nan::New("init_channel").ToLocalChecked(),
  //              Nan::New<v8::FunctionTemplate>(PwmChannelInit)->GetFunction(context));

  // exports->Set(Nan::New("shutdown_channel").ToLocalChecked(),
  //              Nan::New<v8::FunctionTemplate>(PwmChannelShutdown)->GetFunction(context));

  // exports->Set(Nan::New("add_gpio").ToLocalChecked(),
  //              Nan::New<v8::FunctionTemplate>(PwmGpioAdd)->GetFunction(context));

  // exports->Set(Nan::New("set_width").ToLocalChecked(),
  //              Nan::New<v8::FunctionTemplate>(PwmGpioSetWidth)->GetFunction(context));

  // exports->Set(Nan::New("release_gpio").ToLocalChecked(),
  //              Nan::New<v8::FunctionTemplate>(PwmGpioRelease)->GetFunction(context));

  // exports->Set(Nan::New("host_is_model_pi4").ToLocalChecked(),
  //              Nan::New<v8::FunctionTemplate>(PwmHostIsPi4)->GetFunction(context));

  // exports->Set(Nan::New("set_log_level").ToLocalChecked(),
  //              Nan::New<v8::FunctionTemplate>(PwmSetLogLevel)->GetFunction(context));
}

NODE_MODULE(rpiopwm, Init)

} // namespace

