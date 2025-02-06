import React, { Component } from "react";
import { Menu, Spin } from "antd";
import { Routes, Route, Link, useLocation } from "react-router-dom";
import AiDrive from "./AiDrive";
import AiSample from "./AiSample";
import AiTrain from "./AiTrain";
import { Ai } from "../unit";


const menu = [
  { key: "sample", name: "采集样本" },
  { key: "train", name: "学习训练" },
  { key: "drive", name: "自动驾驶" },
];

export default class AiView extends Component {
  constructor(props) {
    super(props);
    this.state = {
      learnForm: {},
      exampleList: [],
      sampleList: [],
      loading: false,
      isPredicting: false,
      loss: 0,
    };
  }

  async componentDidMount() {
    this.setState({ loading: "加载 MobileNet..." });
    const ai = await new Ai();
    this.setState({ loading: false, ai });
  }


  menuItem = ({ key, name }) => {
    return (
      <Menu.Item key={key}>
        <Link to={`./${key}`}>{name}</Link>
      </Menu.Item>
    );
  };

  render() {
    const {
      state: { sampleList, ai, loading },
      props: { controller, cameraEnabled, canvasRef },
      menuItem,
    } = this;

    return (
      <Spin spinning={loading} tip={loading}>
        <Menu
          selectedKeys={[location.pathname.split('/').pop()]}
          mode="horizontal"
        >
          {menu.map((i) => menuItem(i))}
        </Menu>
        <Routes>
          <Route path="sample" element={<AiSample onFinish={(sampleList) => this.setState({ sampleList })} sampleList={sampleList} canvasRef={canvasRef} cameraEnabled={cameraEnabled} controller={controller} />} />
          <Route path="train" element={<AiTrain sampleList={sampleList} controller={controller} ai={ai} />} />
          <Route path="drive" element={<AiDrive controller={controller} canvasRef={canvasRef} cameraEnabled={cameraEnabled} ai={ai} />} />
        </Routes>
      </Spin>
    );
  }
}
