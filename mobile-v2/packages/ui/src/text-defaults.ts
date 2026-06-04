export function textSelectableProp(selectable?: boolean): { selectable?: boolean } {
  return selectable === undefined ? {} : { selectable };
}
