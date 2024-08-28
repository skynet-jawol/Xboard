const { configDir } = require("../unit");
const { join } = require("path");
const { existsSync, mkdirSync } = require("fs");
const audioPlayer = require("../audioPlayer");
const status = require("../status");
const {
  SpeechSynthesizer,
  SpeechConfig,
  AudioConfig,
  SpeechSynthesisOutputFormat,
} = require("@azure/cognitiveservices-speech-sdk");

// 配置 Azure AI 语音服务
const key = "YOUR_AZURE_SPEECH_KEY"; // 替换为你的 Azure 语音服务密钥
const region = "YOUR_AZURE_REGION"; // 替换为你的 Azure 区域
const speechConfig = SpeechConfig.fromSubscription(key, region);
speechConfig.speechSynthesisOutputFormat =
  SpeechSynthesisOutputFormat.Audio16Khz32KBitRateMonoMp3; // 设置输出格式
speechConfig.speechSynthesisLanguage = "zh-CN"; // 设置语言

// 定义 TTS 函数，将文本转换为音频文件
const tts = (text, filePath) => {
  logger.info(`TTS SDK: ${text} ==> "${filePath}"`);
  const audioConfig = AudioConfig.fromAudioFileOutput(filePath);
  const synthesizer = new SpeechSynthesizer(speechConfig, audioConfig);
  return new Promise((resolve, reject) => {
    synthesizer.speakTextAsync(
      text,
      (result) => {
        if (result.reason === ResultReason.SynthesizingAudioCompleted) {
          logger.info("TTS SDK: success ");
          synthesizer.close();
          resolve();
        } else {
          logger.error(`TTS Error: ${result.errorDetails}`);
          synthesizer.close();
          reject(new Error(result.errorDetails));
        }
      },
      (error) => {
        logger.error(`TTS Error: ${error.message}`);
        synthesizer.close();
        reject(error);
      }
    );
  });
};

// 如果 TTS 目录不存在，则创建该目录
if (!existsSync(`${configDir}/tts`)) {
  mkdirSync(`${configDir}/tts`);
}

// 定义 speak 函数，播放指定文本的音频
const speak = async (text = "一起玩网络遥控车", options = {}) => {
  logger.info("TTS play: " + text);

  // 检查是否开启 TTS 功能
  if (status.argv && !status.argv.tts) {
    logger.info("未开启 TTS");
    return;
  }

  const { time = 1, reg = "0", stop = false } = options;

  const filePath = join(`${configDir}/tts/`, text) + ".mp3";
  try {
    // 如果音频文件不存在，则生成该文件
    if (!existsSync(filePath)) {
      try {
        await tts(text, filePath);
      } catch (e) {
        logger.error(`TTS Error: ${e.message}`);
        throw e;
      }
    }
    // 播放生成的音频文件
    await audioPlayer.playFile(filePath, { stop });
  } catch (e) {
    logger.info("播放声音错误：", e);
  }
};

// 将 tts 函数添加到 speak 对象上
speak.tts = tts;

// 导出 speak 函数
module.exports = speak;
