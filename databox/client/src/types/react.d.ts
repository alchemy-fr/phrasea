export type StateSetterArg<S> = S | StateSetterArgResolver<S>;
export type StateSetterArgResolver<S> = (prevState: S) => S;
