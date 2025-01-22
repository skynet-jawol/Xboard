import React, { useState, useEffect } from 'react';
import {
  Table,
  Card,
  Form,
  Input,
  Select,
  DatePicker,
  Button,
  Space,
  message,
  Statistic,
  Row,
  Col
} from 'antd';
import { DownloadOutlined, SearchOutlined, ReloadOutlined } from '@ant-design/icons';
import axios from 'axios';
import moment from 'moment';

const { RangePicker } = DatePicker;
const { Option } = Select;

const LogViewer = () => {
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState([]);
  const [pagination, setPagination] = useState({
    current: 1,
    pageSize: 20,
    total: 0
  });
  const [stats, setStats] = useState({
    levelStats: [],
    moduleStats: []
  });
  const [form] = Form.useForm();

  // 列定义
  const columns = [
    {
      title: '时间',
      dataIndex: 'timestamp',
      key: 'timestamp',
      render: (text) => moment(text).format('YYYY-MM-DD HH:mm:ss.SSS')
    },
    {
      title: '级别',
      dataIndex: 'level',
      key: 'level',
      width: 100,
      render: (text) => (
        <Select value={text} style={{ width: '100%' }} bordered={false}>
          <Option value="fatal">FATAL</Option>
          <Option value="error">ERROR</Option>
          <Option value="warn">WARN</Option>
          <Option value="info">INFO</Option>
          <Option value="debug">DEBUG</Option>
          <Option value="trace">TRACE</Option>
        </Select>
      )
    },
    {
      title: '模块',
      dataIndex: 'module',
      key: 'module',
      width: 120
    },
    {
      title: '内容',
      dataIndex: 'message',
      key: 'message',
      ellipsis: true
    },
    {
      title: '详细数据',
      dataIndex: 'data',
      key: 'data',
      width: 100,
      render: (text) => text ? (
        <Button type="link" onClick={() => message.info(JSON.stringify(text, null, 2))}>
          查看
        </Button>
      ) : null
    }
  ];

  // 获取日志数据
  const fetchLogs = async (params = {}) => {
    setLoading(true);
    try {
      const { data: response } = await axios.get('/api/logs/query', { params });
      setData(response.data.list);
      setPagination({
        ...pagination,
        current: response.data.page,
        total: response.data.total
      });
    } catch (error) {
      message.error('获取日志数据失败');
    }
    setLoading(false);
  };

  // 获取统计数据
  const fetchStats = async (params = {}) => {
    try {
      const { data: response } = await axios.get('/api/logs/stats', { params });
      setStats(response.data);
    } catch (error) {
      message.error('获取统计数据失败');
    }
  };

  // 导出日志
  const exportLogs = async (format) => {
    try {
      const values = await form.validateFields();
      const params = {
        ...values,
        format,
        startTime: values.timeRange?.[0]?.valueOf(),
        endTime: values.timeRange?.[1]?.valueOf()
      };
      window.location.href = `/api/logs/export?${new URLSearchParams(params)}`;
    } catch (error) {
      message.error('导出失败');
    }
  };

  // 表格变化处理
  const handleTableChange = (pagination, filters, sorter) => {
    fetchLogs({
      ...form.getFieldsValue(),
      page: pagination.current,
      pageSize: pagination.pageSize
    });
  };

  // 搜索处理
  const handleSearch = async () => {
    try {
      const values = await form.validateFields();
      const params = {
        ...values,
        startTime: values.timeRange?.[0]?.valueOf(),
        endTime: values.timeRange?.[1]?.valueOf(),
        page: 1
      };
      fetchLogs(params);
      fetchStats(params);
    } catch (error) {
      message.error('搜索失败');
    }
  };

  // 重置处理
  const handleReset = () => {
    form.resetFields();
    fetchLogs();
    fetchStats();
  };

  useEffect(() => {
    fetchLogs();
    fetchStats();
  }, []);

  return (
    <div style={{ padding: 24 }}>
      <Card title="日志统计" style={{ marginBottom: 24 }}>
        <Row gutter={24}>
          <Col span={12}>
            <Card title="日志级别分布">
              <Row gutter={16}>
                {stats.levelStats.map(stat => (
                  <Col span={8} key={stat.level}>
                    <Statistic title={stat.level.toUpperCase()} value={stat.count} />
                  </Col>
                ))}
              </Row>
            </Card>
          </Col>
          <Col span={12}>
            <Card title="模块分布">
              <Row gutter={16}>
                {stats.moduleStats.map(stat => (
                  <Col span={8} key={stat.module}>
                    <Statistic title={stat.module} value={stat.count} />
                  </Col>
                ))}
              </Row>
            </Card>
          </Col>
        </Row>
      </Card>

      <Card title="日志查询">
        <Form form={form} layout="inline" style={{ marginBottom: 24 }}>
          <Form.Item name="timeRange">
            <RangePicker
              showTime
              style={{ width: 380 }}
              placeholder={['开始时间', '结束时间']}
            />
          </Form.Item>
          <Form.Item name="level">
            <Select style={{ width: 120 }} placeholder="日志级别">
              <Option value="fatal">FATAL</Option>
              <Option value="error">ERROR</Option>
              <Option value="warn">WARN</Option>
              <Option value="info">INFO</Option>
              <Option value="debug">DEBUG</Option>
              <Option value="trace">TRACE</Option>
            </Select>
          </Form.Item>
          <Form.Item name="module">
            <Select style={{ width: 120 }} placeholder="模块">
              <Option value="controller">控制器</Option>
              <Option value="video">视频</Option>
              <Option value="audio">音频</Option>
              <Option value="gps">GPS</Option>
              <Option value="network">网络</Option>
              <Option value="system">系统</Option>
            </Select>
          </Form.Item>
          <Form.Item name="keyword">
            <Input placeholder="关键字搜索" style={{ width: 200 }} />
          </Form.Item>
          <Form.Item>
            <Space>
              <Button type="primary" icon={<SearchOutlined />} onClick={handleSearch}>
                搜索
              </Button>
              <Button icon={<ReloadOutlined />} onClick={handleReset}>
                重置
              </Button>
              <Button icon={<DownloadOutlined />} onClick={() => exportLogs('json')}>
                导出JSON
              </Button>
              <Button icon={<DownloadOutlined />} onClick={() => exportLogs('csv')}>
                导出CSV
              </Button>
            </Space>
          </Form.Item>
        </Form>

        <Table
          columns={columns}
          dataSource={data}
          rowKey="id"
          pagination={pagination}
          loading={loading}
          onChange={handleTableChange}
        />
      </Card>
    </div>
  );
};

export default LogViewer;