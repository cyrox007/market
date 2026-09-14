import { api } from '../lib/api';
import { useMenuTree } from './useMenuTree';

/** Один ключ на всё приложение: шапка на всех страницах — запрос должен быть один */
export const ROOM_TREE_KEY = '/api/rooms/tree';

/** Дерево комнат для шапки. Узлы в том же формате, что категории (Category). */
export function useRoomTree() {
  const { tree, isLoading } = useMenuTree(ROOM_TREE_KEY, 'header:room-tree', () => api.rooms.tree());

  return { rooms: tree, isLoading };
}
