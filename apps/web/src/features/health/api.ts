import { useQuery } from '@tanstack/react-query'

import { apiClient } from '../../api/client'

type HealthResponse = {
  data: {
    status: 'ok'
    service: string
    environment: string
    timestamp: string
  }
}

export const healthQueryKey = ['health'] as const

export function useHealthQuery() {
  return useQuery({
    queryKey: healthQueryKey,
    queryFn: async () => {
      const response = await apiClient.get<HealthResponse>('/health')
      return response.data.data
    },
    retry: 1,
    refetchInterval: 30_000,
  })
}
