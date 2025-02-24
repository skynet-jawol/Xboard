package service

import (
	"context"
	"fmt"
	"time"

	"x-ui/database/model"
	"x-ui/proto"
	"x-ui/xray"

	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

type NodeManagerService struct {
	proto.UnimplementedNodeManagerServer
	xrayService    *XrayService
	inboundService *InboundService
	serverService  *ServerService
}

func NewNodeManagerService(xs *XrayService, is *InboundService, ss *ServerService) *NodeManagerService {
	return &NodeManagerService{
		xrayService:    xs,
		inboundService: is,
		serverService:  ss,
	}
}

// SyncUsers 同步用户配置到节点
func (s *NodeManagerService) SyncUsers(ctx context.Context, req *proto.SyncUsersRequest) (*proto.SyncUsersResponse, error) {
	try {
		// 更新用户配置
		for _, user := range req.Users {
			// 转换为inbound配置
			inbound := &model.Inbound{
				UserId:     user.Id,
				Email:      user.Email,
				Uuid:       user.Uuid,
				SpeedLimit: user.SpeedLimit,
				Enable:     user.Enable,
			}
			
			// 更新或创建inbound
			if err := s.inboundService.UpdateInbound(inbound); err != nil {
				return nil, status.Errorf(codes.Internal, "Failed to update inbound: %v", err)
			}
		}

		// 重启Xray服务以应用更改
		if err := s.xrayService.RestartXray(false); err != nil {
			return nil, status.Errorf(codes.Internal, "Failed to restart xray: %v", err)
		}

		return &proto.SyncUsersResponse{
			Success: true,
			Message: "Users synced successfully",
		}, nil
	} catch (err error) {
		return &proto.SyncUsersResponse{
			Success: false,
			Message: fmt.Sprintf("Failed to sync users: %v", err),
		}, nil
	}
}

// GetNodeStatus 获取节点状态
func (s *NodeManagerService) GetNodeStatus(ctx context.Context, req *proto.GetNodeStatusRequest) (*proto.GetNodeStatusResponse, error) {
	try {
		// 获取系统状态
		status := s.serverService.GetStatus()

		// 构建响应
		return &proto.GetNodeStatusResponse{
			Success: true,
			SystemLoad: &proto.SystemLoad{
				CpuUsage:     status.Cpu,
				MemoryUsage:  float64(status.Mem.Current) / float64(status.Mem.Total),
				DiskUsage:    float64(status.Disk.Current) / float64(status.Disk.Total),
				LoadAverages: status.Loads,
			},
			XrayVersion: status.Xray.Version,
			XrayStatus:  string(status.Xray.State),
		}, nil
	} catch (err error) {
		return &proto.GetNodeStatusResponse{
			Success: false,
			Message: fmt.Sprintf("Failed to get node status: %v", err),
		}, nil
	}
}

// GetSystemStats 获取系统统计信息
func (s *NodeManagerService) GetSystemStats(ctx context.Context, req *proto.GetSystemStatsRequest) (*proto.GetSystemStatsResponse, error) {
	try {
		// 获取系统状态
		status := s.serverService.GetStatus()

		// 构建响应
		return &proto.GetSystemStatsResponse{
			Success: true,
			SystemLoad: &proto.SystemLoad{
				CpuUsage:     status.Cpu,
				MemoryUsage:  float64(status.Mem.Current) / float64(status.Mem.Total),
				DiskUsage:    float64(status.Disk.Current) / float64(status.Disk.Total),
				LoadAverages: status.Loads,
			},
			NetworkStats: &proto.NetworkStats{
				TcpConnections: uint64(status.TcpCount),
				UdpConnections: uint64(status.UdpCount),
				NetworkIoUp:    status.NetIO.Up,
				NetworkIoDown:  status.NetIO.Down,
			},
			Uptime: status.Uptime,
		}, nil
	} catch (err error) {
		return &proto.GetSystemStatsResponse{
			Success: false,
			Message: fmt.Sprintf("Failed to get system stats: %v", err),
		}, nil
	}
}

// UpdateNodeConfig 更新节点配置
func (s *NodeManagerService) UpdateNodeConfig(ctx context.Context, req *proto.UpdateNodeConfigRequest) (*proto.UpdateNodeConfigResponse, error) {
	try {
		// 更新inbound配置
		config := req.GetConfig()
		inbound := &model.Inbound{
			Protocol: config.Protocol,
			Port:     int(config.Port),
			Settings: config.Settings,
		}

		// 更新配置
		if err := s.inboundService.UpdateInbound(inbound); err != nil {
			return nil, status.Errorf(codes.Internal, "Failed to update config: %v", err)
		}

		// 重启Xray服务以应用更改
		if err := s.xrayService.RestartXray(false); err != nil {
			return nil, status.Errorf(codes.Internal, "Failed to restart xray: %v", err)
		}

		return &proto.UpdateNodeConfigResponse{
			Success: true,
			Message: "Node config updated successfully",
		}, nil
	} catch (err error) {
		return &proto.UpdateNodeConfigResponse{
			Success: false,
			Message: fmt.Sprintf("Failed to update node config: %v", err),
		}, nil
	}
}

// GetUserTraffic 获取用户流量统计
func (s *NodeManagerService) GetUserTraffic(ctx context.Context, req *proto.GetUserTrafficRequest) (*proto.GetUserTrafficResponse, error) {
	try {
		// 获取用户流量统计
		start := time.Unix(req.StartTime, 0)
		end := time.Unix(req.EndTime, 0)
		stats, err := s.inboundService.GetUserTraffic(req.UserId, start, end)
		if err != nil {
			return nil, status.Errorf(codes.Internal, "Failed to get user traffic: %v", err)
		}

		return &proto.GetUserTrafficResponse{
			Success: true,
			Stats: &proto.TrafficStats{
				UpTraffic:   stats.Up,
				DownTraffic: stats.Down,
			},
		}, nil
	} catch (err error) {
		return &proto.GetUserTrafficResponse{
			Success: false,
			Message: fmt.Sprintf("Failed to get user traffic: %v", err),
		}, nil
	}
}